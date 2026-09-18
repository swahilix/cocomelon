<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuyingGuide;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BuyingGuideController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        return Inertia::render('Admin/Guides/Index', [
            'guides' => BuyingGuide::query()
                ->with('category:id,name')
                ->when(in_array($status, ['draft', 'published'], true), fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
            'counts' => [
                'all' => BuyingGuide::count(),
                'published' => BuyingGuide::where('status', 'published')->count(),
                'draft' => BuyingGuide::where('status', 'draft')->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Guides/Form', $this->formData());
    }

    public function edit(BuyingGuide $guide): Response
    {
        return Inertia::render('Admin/Guides/Form', [
            ...$this->formData(),
            'guide' => $guide,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $guide = BuyingGuide::create($this->validated($request));

        return redirect()->route('admin.guides.edit', $guide)->with('success', 'Buying guide created successfully.');
    }

    public function update(Request $request, BuyingGuide $guide): RedirectResponse
    {
        $guide->update($this->validated($request, $guide));

        return back()->with('success', 'Buying guide updated successfully.');
    }

    public function destroy(BuyingGuide $guide): RedirectResponse
    {
        $guide->delete();

        return redirect()->route('admin.guides.index')->with('success', 'Buying guide moved out of the public content library.');
    }

    private function formData(): array
    {
        return [
            'categories' => Category::productType()->active()->orderBy('name')->get(['id', 'name']),
            'products' => Product::active()->orderBy('name')->take(500)->get(['id', 'name', 'sku']),
        ];
    }

    private function validated(Request $request, ?BuyingGuide $guide = null): array
    {
        $data = $request->validate([
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('type', 'product')->whereNull('deleted_at'))],
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('buying_guides', 'slug')->ignore($guide)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'min:80', 'max:50000'],
            'cover_image' => ['nullable', 'url', 'max:2048'],
            'related_product_ids' => ['nullable', 'array', 'max:20'],
            'related_product_ids.*' => ['integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'featured' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:180'],
        ]);

        $data['slug'] = trim((string) ($data['slug'] ?? '')) ?: Str::slug($data['title']);
        $data['related_product_ids'] = array_values(array_unique(array_map('intval', $data['related_product_ids'] ?? [])));

        return $data;
    }
}
