<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\ImageOptimizationService;
use App\Services\InventoryService;
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

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', $this->formData());
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Form', [
            ...$this->formData(),
            'product' => $product->load(['brand', 'category']),
        ]);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $this->validated($request);
        $openingStock = (int) ($validated['stock_quantity'] ?? 0);
        $data = $this->prepareProductData($this->handleImages($request, $validated));
        $data['stock_quantity'] = 0;

        DB::transaction(function () use ($data, $openingStock, $request, $inventory): void {
            $product = Product::create($data);

            if ($product->track_stock && $openingStock > 0) {
                $inventory->openingBalance($product, $openingStock, 'Initial stock entered when product was created.', $request->user()?->id);
            }
        });

        return redirect()->route('admin.products.create')->with('success', 'Product created successfully. The form is ready for the next product.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->prepareProductData($this->handleImages($request, $this->validated($request, $product), $product));

        // Inventory is intentionally isolated from product metadata edits. Stock changes must use InventoryService.
        unset($data['stock_quantity']);

        $product->update($data);

        return back()->with('success', 'Product updated successfully. Stock quantity is managed separately from Stock Management → Inventory.');
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
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        if (! $product && blank($request->input('sku'))) {
            $request->merge(['sku' => $this->generateSku()]);
        }

        return $request->validate([
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
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after:sale_starts_at'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
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
            'warranty' => ['nullable', 'string', 'max:190'],
            'specifications_text' => ['nullable', 'string', 'max:10000'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:180'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
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
        unset($data['specifications_text']);
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
        unset($data['primary_image_url'], $data['gallery_urls']);

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
                            'title' => $data['name'].' product gallery image '.($index + 1).' | Prinx Electronics',
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
            'title' => $data['name'].' | Prinx Electronics Tanzania',
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
                'Prinx Electronics',
                'electronics Tanzania',
                'Dodoma Tanzania',
            ])),
        ];
    }
}
