<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductUnitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BarcodeController extends Controller
{
    public function index(ProductUnitService $units): Response
    {
        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('track_stock', true)
            ->orderBy('name')
            ->get(['id', 'category_id', 'brand_id', 'name', 'sku', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'stock_quantity', 'track_stock', 'status']);

        foreach ($products as $product) {
            $units->syncToStock($product);
        }

        $unitRows = DB::table('product_units')
            ->whereIn('product_id', $products->pluck('id'))
            ->where('status', 'available')
            ->orderBy('product_id')
            ->orderBy('unit_number')
            ->get()
            ->groupBy('product_id');

        return Inertia::render('Admin/Barcodes/Index', [
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => $product->current_price,
                'stock_quantity' => (int) $product->stock_quantity,
                'status' => $product->status,
                'category' => $product->category?->name,
                'brand' => $product->brand?->name,
                'units' => collect($unitRows->get($product->id, []))->map(fn ($unit) => [
                    'id' => $unit->id,
                    'unit_number' => (int) $unit->unit_number,
                    'barcode' => $unit->barcode,
                    'print_count' => (int) $unit->print_count,
                    'last_printed_at' => $unit->last_printed_at,
                ])->values(),
            ])->values(),
        ]);
    }

    public function printed(Request $request, ProductUnitService $units): RedirectResponse
    {
        $validated = $request->validate([
            'unit_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'unit_ids.*' => ['required', 'integer', 'exists:product_units,id'],
        ]);

        $units->markPrinted($validated['unit_ids']);

        return back();
    }
}
