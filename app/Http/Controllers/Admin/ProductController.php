<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImageOptimizationService;
use App\Services\InventoryService;
use App\Services\ProductIntelligence\OpenAiResponses;
use App\Services\ProductPricingService;
use App\Services\WarrantySmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ImageOptimizationService $images)
    {
    }

    public function index(Request $request): Response
    {
        $trash = $request->input('view') === 'trash';

        $query = $trash ? Product::onlyTrashed() : Product::query();

        $products = $query
            ->with(['brand:id,name', 'category:id,name'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($search) => $search
                ->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('sku', 'like', '%'.$request->search.'%')))
            ->when(! $trash && $request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when(! $trash && $request->filled('sync'), fn ($q) => $q->where('merchant_sync_status', $request->sync))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->orderByDesc($trash ? 'deleted_at' : 'created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => Category::productType()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'status', 'sync', 'category', 'view'),
            'counts' => [
                'active' => Product::count(),
                'published' => Product::active()->count(),
                'drafts' => Product::where('status', 'draft')->count(),
                'trash' => Product::onlyTrashed()->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $saved = $request->integer('saved') ? Product::query()->find($request->integer('saved'), ['id', 'name', 'slug']) : null;

        return Inertia::render('Admin/Products/Form', [
            ...$this->formData(),
            'savedProduct' => $saved ? ['id' => $saved->id, 'name' => $saved->name, 'slug' => $saved->slug] : null,
        ]);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Form', [
            ...$this->formData(),
            'product' => $product->load(['brand', 'category', 'variants']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $variants = $validated['variants'] ?? [];
        $data = $this->prepareProductData($this->handleImages($request, $validated));

        // Step 1 records product details only. Prices and stock are added in
        // step 2 through opening stock or a purchase.
        $data['price'] = 0;
        $data['sale_price'] = null;
        $data['sale_starts_at'] = null;
        $data['sale_ends_at'] = null;
        $data['stock_quantity'] = 0;

        $product = DB::transaction(function () use ($data, $variants, $request): Product {
            $product = Product::create($data);

            if ($this->syncVariants($request, $product, $variants) !== null) {
                $product->forceFill(['track_stock' => true])->save();
            }

            return $product;
        });

        // Products are entered on their own; stock and prices are added later
        // from the Add stock page.
        return redirect()
            ->route('admin.products.create', ['saved' => $product->id])
            ->with('success', $product->status === 'active'
                ? $product->name.' saved. It shows in purchases and stock now, and on the store once you add its price through Add stock or a purchase.'
                : $product->name.' saved as a draft. Add its stock and price from Add stock.');
    }

    public function update(Request $request, Product $product, InventoryService $inventory, ProductPricingService $pricing): RedirectResponse
    {
        $validated = $this->validated($request, $product);
        $variants = $validated['variants'] ?? [];
        $data = $this->prepareProductData($this->handleImages($request, $validated, $product));

        DB::transaction(function () use ($request, $product, $data, $variants, $inventory, $pricing): void {
            $product->update($data);
            $variantStock = $this->syncVariants($request, $product, $variants);

            if ($variantStock !== null) {
                if (! $product->track_stock) {
                    $product->forceFill(['track_stock' => true])->save();
                }

                $inventory->adjust(
                    $product,
                    $variantStock,
                    'Variant stock sync',
                    'Parent stock synchronized to the sum of active product variants.',
                    $request->user()?->id,
                );

                $pricing->syncParentFromVariants($product);
            }
        });

        return back()->with('success', 'Product details updated. Use Stock & pricing to change prices or add stock.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product moved to Trash. It can be restored at any time.');
    }

    public function restore(int $product): RedirectResponse
    {
        $item = Product::onlyTrashed()->findOrFail($product);
        $item->restore();

        return back()->with('success', 'Product restored successfully.');
    }

    private function formData(): array
    {
        return [
            'categories' => Category::productType()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'aiFormatterEnabled' => (bool) config('services.openai.product_formatter_enabled') && filled(config('services.openai.api_key')),
            'smartFill' => [
                'barcode' => (bool) config('services.upcitemdb.enabled', true) || app(OpenAiResponses::class)->enabled(),
                'ai' => app(OpenAiResponses::class)->enabled(),
            ],
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        if (! $product && blank($request->input('sku'))) {
            $request->merge(['sku' => $this->generateSku()]);
        }

        $validated = $request->validate([
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('type', 'product')->whereNull('deleted_at')),
            ],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('products', 'slug')->ignore($product)],
            'sku' => ['required', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:30000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'track_stock' => ['boolean'],
            'condition' => ['required', 'in:new,refurbished,used'],
            'status' => ['required', 'in:draft,active,archived'],
            'featured' => ['boolean'],
            'merchant_sync_enabled' => ['boolean'],
            'primary_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:16384', 'dimensions:min_width=500,min_height=500'],
            'primary_image_url' => ['nullable', 'url', 'max:2048'],
            'gallery' => ['nullable', 'array', 'max:10'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:16384', 'dimensions:min_width=500,min_height=500'],
            'gallery_urls' => ['nullable', 'string', 'max:10000'],
            'gtin' => ['nullable', 'string', 'max:50'],
            'mpn' => ['nullable', 'string', 'max:100'],
            'google_product_category' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'max:255'],
            'item_group_id' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female,unisex'],
            'age_group' => ['nullable', 'in:newborn,infant,toddler,kids,adult'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'shipping_price' => ['nullable', 'numeric', 'min:0'],
            'shipping_weight' => ['nullable', 'numeric', 'min:0'],
            'warranty_duration_value' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_with:warranty_duration_unit'],
            'warranty_duration_unit' => ['nullable', 'in:days,months,years', 'required_with:warranty_duration_value'],
            'specifications_text' => ['nullable', 'string', 'max:10000'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:180'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'variants' => ['nullable', 'array', 'max:50'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:100', 'distinct'],
            'variants.*.label' => ['nullable', 'string', 'max:140'],
            'variants.*.color' => ['nullable', 'string', 'max:100'],
            'variants.*.storage' => ['nullable', 'string', 'max:100'],
            'variants.*.size' => ['nullable', 'string', 'max:100'],
            'variants.*.image_url' => ['nullable', 'url', 'max:2048'],
            'variants.*.image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $value = $validated['warranty_duration_value'] ?? null;
        $unit = $validated['warranty_duration_unit'] ?? null;
        $validated['warranty_duration_value'] = $value && $unit ? (int) $value : null;
        $validated['warranty_duration_unit'] = $value && $unit ? $unit : null;
        $validated['warranty'] = $value && $unit
            ? app(WarrantySmsService::class)->durationLabel((int) $value, $unit).' warranty'
            : null;

        return $validated;
    }

    private function syncVariants(Request $request, Product $product, array $variants): ?int
    {
        $existing = $product->variants()->withTrashed()->get()->keyBy('id');

        if ($variants === []) {
            if ($existing->isNotEmpty()) {
                $product->variants()->delete();
            }

            return null;
        }

        $kept = [];
        $activeStock = 0;

        foreach (array_values($variants) as $index => $row) {
            $variantId = filled($row['id'] ?? null) ? (int) $row['id'] : null;
            $variant = $variantId ? $existing->get($variantId) : null;

            if ($variantId && (! $variant || (int) $variant->product_id !== (int) $product->id)) {
                throw ValidationException::withMessages(["variants.$index.id" => 'That variant does not belong to this product.']);
            }

            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            $duplicate = ProductVariant::withTrashed()
                ->where('sku', $sku)
                ->when($variant, fn ($query) => $query->whereKeyNot($variant->id))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(["variants.$index.sku" => 'Variant SKU must be unique.']);
            }

            if (! collect([$row['label'] ?? null, $row['color'] ?? null, $row['storage'] ?? null, $row['size'] ?? null])->filter(fn ($value) => filled($value))->count()) {
                throw ValidationException::withMessages(["variants.$index.label" => 'Add at least one variant label, color, storage or size value.']);
            }

            $payload = [
                'product_id' => $product->id,
                'sku' => $sku,
                'label' => $row['label'] ?? null,
                'color' => $row['color'] ?? null,
                'storage' => $row['storage'] ?? null,
                'size' => $row['size'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? false),
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ];

            if ($request->hasFile("variants.$index.image_file")) {
                $oldImage = $variant?->image;
                try {
                    $payload['image'] = $this->images->storeProduct(
                        $request->file("variants.$index.image_file"),
                        'products/variants',
                        $product->name.' '.$sku,
                        [
                            'title' => $product->name.' '.$sku.' variant | Prinx Tech',
                            'description' => $product->meta_description ?: $product->short_description,
                            'identifier' => $sku,
                            'source_url' => route('products.show', $product),
                            'brand' => $product->brand?->name,
                            'category' => $product->category?->name,
                        ],
                    );
                    $this->images->deleteLocal($oldImage);
                } catch (Throwable $exception) {
                    throw ValidationException::withMessages(["variants.$index.image_file" => $exception->getMessage()]);
                }
            } elseif (filled($row['image_url'] ?? null)) {
                $payload['image'] = trim((string) $row['image_url']);
            } elseif ($variant) {
                $payload['image'] = $variant->image;
            }

            if ($variant) {
                if ($variant->trashed()) $variant->restore();
                $variant->update($payload);
            } else {
                // New variants start without stock or price; both are added in
                // Stock & pricing through opening stock or a purchase.
                $variant = ProductVariant::create([...$payload, 'price' => null, 'sale_price' => null, 'stock_quantity' => 0]);
            }

            $kept[] = $variant->id;
            if ($variant->is_active) {
                $activeStock += (int) $variant->stock_quantity;
            }
        }

        if ($variants !== []) {
            $product->variants()->whereNotIn('id', $kept)->delete();
            return $activeStock;
        }

        return null;
    }

    private function generateSku(): string
    {
        do {
            $sku = 'PRX-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (Product::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }

    private function prepareProductData(array $data): array
    {
        $text = trim((string) ($data['specifications_text'] ?? ''));
        unset($data['specifications_text'], $data['variants']);
        $specifications = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            if (! str_contains($line, ':')) continue;
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            if ($key !== '' && $value !== '') $specifications[$key] = $value;
        }
        $data['specifications'] = $specifications ?: null;
        return $data;
    }

    private function handleImages(Request $request, array $data, ?Product $product = null): array
    {
        // Never allow empty upload controls to erase saved media. A product image
        // changes only when a real replacement file or explicit replacement URL is supplied.
        unset(
            $data['primary_image'],
            $data['primary_image_url'],
            $data['gallery'],
            $data['gallery_urls']
        );

        $metadata = $this->imageMetadata($data);

        if ($request->hasFile('primary_image')) {
            $oldImage = $product?->primary_image;

            try {
                $data['primary_image'] = $this->images->storeProduct(
                    $request->file('primary_image'),
                    'products',
                    $data['name'],
                    $metadata
                );
            } catch (Throwable $exception) {
                throw ValidationException::withMessages(['primary_image' => $exception->getMessage()]);
            }

            $this->images->deleteLocal($oldImage);
        } elseif ($request->filled('primary_image_url')) {
            $data['primary_image'] = $request->string('primary_image_url')->toString();
        }

        $gallery = $product?->gallery ?? [];

        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $index => $image) {
                try {
                    $gallery[] = $this->images->storeProduct(
                        $image,
                        'products/gallery',
                        $data['name'].' gallery '.($index + 1),
                        [
                            ...$metadata,
                            'title' => $data['name'].' product gallery image '.($index + 1).' | Prinx Tech',
                        ]
                    );
                } catch (Throwable $exception) {
                    throw ValidationException::withMessages(['gallery' => $exception->getMessage()]);
                }
            }
        }

        if ($request->filled('gallery_urls')) {
            $gallery = array_merge($gallery, array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $request->gallery_urls)))));
        }

        $data['gallery'] = array_values(array_unique(array_slice($gallery, 0, 10)));

        return $data;
    }

    private function imageMetadata(array $data): array
    {
        $category = Category::find($data['category_id'] ?? null);
        $brand = Brand::find($data['brand_id'] ?? null);
        $description = trim((string) ($data['meta_description'] ?? $data['short_description'] ?? $data['description'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? '')) ?: Str::slug((string) ($data['name'] ?? 'product'));
        $productUrl = rtrim((string) config('app.url'), '/').'/products/'.$slug;

        return [
            'title' => $data['name'].' | Prinx Tech Tanzania',
            'description' => Str::limit($description, 300, ''),
            'identifier' => (string) ($data['sku'] ?? ''),
            'source_url' => $productUrl,
            'brand' => $brand?->name,
            'category' => $category?->name,
            'keywords' => array_values(array_filter([
                $data['name'] ?? null,
                $brand?->name,
                $category?->name,
                $data['sku'] ?? null,
                ($data['name'] ?? null) ? 'buy '.$data['name'].' Tanzania' : null,
                $category?->name ? $category->name.' Tanzania' : null,
                'Prinx Tech',
                'electronics Tanzania',
                'Dodoma Tanzania',
            ])),
        ];
    }
}
