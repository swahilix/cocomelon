<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Gtin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GtinController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            ->with(['brand:id,name', 'category:id,name'])
            ->where(function ($query): void {
                $query->whereNull('gtin')->orWhere('gtin', '');
            })
            ->orderBy('name')
            ->get([
                'id', 'name', 'slug', 'sku', 'primary_image', 'gallery', 'brand_id', 'category_id',
                'stock_quantity', 'track_stock', 'status',
            ]);

        return Inertia::render('Admin/Products/Gtin', [
            'products' => $products,
            'missingCount' => $products->count(),
            'withGtinCount' => Product::query()->whereNotNull('gtin')->where('gtin', '<>', '')->count(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $request->merge([
            'gtin' => trim((string) $request->input('gtin')),
        ]);

        $validated = $request->validate([
            'gtin' => [
                'required',
                'string',
                'regex:/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/',
                Rule::unique('products', 'gtin')->ignore($product),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! Gtin::isValid((string) $value)) {
                        $fail('The scanned barcode is not a valid GTIN. Check the barcode and scan again.');
                    }
                },
            ],
        ], [
            'gtin.regex' => 'GTIN must contain exactly 8, 12, 13 or 14 digits.',
            'gtin.unique' => 'This GTIN is already assigned to another product.',
        ]);

        $product->update(['gtin' => $validated['gtin']]);

        return back()->with('success', 'GTIN saved for '.$product->name.'.');
    }
}
