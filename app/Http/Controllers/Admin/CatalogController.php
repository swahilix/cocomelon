<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Services\ImageOptimizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function __construct(private readonly ImageOptimizationService $images)
    {
    }

    public function index(Request $request): Response
    {
        $showTrash = $request->input('view') === 'trash';
        $categoryQuery = $showTrash ? Category::onlyTrashed() : Category::query();
        $brandQuery = $showTrash ? Brand::onlyTrashed() : Brand::query();

        return Inertia::render('Admin/Catalog', [
            'productCategories' => (clone $categoryQuery)
                ->where('type', 'product')
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'serviceCategories' => (clone $categoryQuery)
                ->where('type', 'service')
                ->withCount('services')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'brands' => $brandQuery->withCount('products')->orderBy('name')->get(),
            'view' => $showTrash ? 'trash' : 'active',
            'counts' => [
                'product_categories' => Category::productType()->count(),
                'service_categories' => Category::serviceType()->count(),
                'brands' => Brand::count(),
                'trash' => Category::onlyTrashed()->count() + Brand::onlyTrashed()->count(),
            ],
        ]);
    }

    public function category(Request $request): RedirectResponse
    {
        $data = $this->categoryData($request);
        $data = $this->handleCategoryImage($request, $data);
        Category::create($data);

        return back()->with('success', ucfirst($data['type']).' category created successfully.');
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $this->categoryData($request, $category);

        if ($data['type'] !== $category->type && ($category->products()->exists() || $category->services()->exists())) {
            return back()->withErrors([
                'type' => 'Move assigned items to another category before changing this category type.',
            ]);
        }

        $data = $this->handleCategoryImage($request, $data, $category);
        $category->update($data);

        return back()->with('success', 'Category updated successfully.');
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', 'Category moved to Trash. Assigned items remain safe and can be restored with the category.');
    }

    public function restoreCategory(int $category): RedirectResponse
    {
        Category::onlyTrashed()->findOrFail($category)->restore();

        return back()->with('success', 'Category restored successfully.');
    }

    public function brand(Request $request): RedirectResponse
    {
        $data = $this->brandData($request);
        $data['is_active'] = true;
        $data = $this->handleBrandLogo($request, $data);
        Brand::create($data);

        return back()->with('success', 'Brand created successfully.');
    }

    public function updateBrand(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->brandData($request, $brand);
        $data = $this->handleBrandLogo($request, $data, $brand);
        $brand->update($data);

        return back()->with('success', 'Brand updated successfully.');
    }

    public function destroyBrand(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return back()->with('success', 'Brand moved to Trash.');
    }

    public function restoreBrand(int $brand): RedirectResponse
    {
        Brand::onlyTrashed()->findOrFail($brand)->restore();

        return back()->with('success', 'Brand restored successfully.');
    }

    private function categoryData(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->ignore($category)],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('categories', 'slug')->ignore($category)],
            'type' => ['required', Rule::in(['product', 'service'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:180'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function handleCategoryImage(Request $request, array $data, ?Category $category = null): array
    {
        // A nullable image input can be present as null in an Inertia update.
        // Remove it from the update payload so the saved image is retained.
        unset($data['image'], $data['image_url']);

        if ($request->hasFile('image')) {
            $oldImage = $category?->image;
            try {
                $data['image'] = $this->images->store(
                    $request->file('image'),
                    'categories',
                    $data['name'].' '.$data['type'].' category',
                    [
                        'title' => $data['name'].' '.$data['type'].' category | Prinx Tech',
                        'description' => trim((string) ($data['description'] ?? 'Browse '.$data['name'].' at Prinx Tech.')),
                        'keywords' => [
                            $data['name'],
                            $data['type'].' category',
                            'Prinx Tech',
                            'Tanzania',
                        ],
                    ]
                );
            } catch (Throwable $exception) {
                throw ValidationException::withMessages(['image' => $exception->getMessage()]);
            }
            $this->images->deleteLocal($oldImage);
        } elseif ($request->filled('image_url')) {
            $data['image'] = $request->string('image_url')->toString();
        }

        return $data;
    }

    private function brandData(Request $request, ?Brand $brand = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('brands', 'name')->ignore($brand)],
            'logo' => [$brand ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
    }

    private function handleBrandLogo(Request $request, array $data, ?Brand $brand = null): array
    {
        if (! $request->hasFile('logo')) {
            unset($data['logo']);
            return $data;
        }

        $oldLogo = $brand?->logo;

        try {
            $data['logo'] = $this->images->store(
                $request->file('logo'),
                'brands',
                $data['name'].' brand logo',
                [
                    'title' => $data['name'].' logo | Prinx Tech',
                    'description' => $data['name'].' brand logo used in the Prinx Tech catalogue.',
                    'keywords' => [$data['name'], 'brand logo', 'Prinx Tech'],
                ]
            );
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['logo' => $exception->getMessage()]);
        }

        $this->images->deleteLocal($oldLogo);

        return $data;
    }
}
