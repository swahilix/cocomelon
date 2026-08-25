<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    public function index(Request $request): Response
    {
        $rows = DB::table('wishlists')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get(['product_id', 'created_at']);

        $products = Product::query()
            ->active()
            ->with(['category', 'brand'])
            ->whereIn('id', $rows->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $items = $rows->map(function (object $row) use ($products): ?array {
            $product = $products->get($row->product_id);
            if (! $product) {
                return null;
            }

            return [
                'id' => (int) $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'short_description' => $product->short_description,
                'price' => (float) $product->price,
                'current_price' => (float) $product->current_price,
                'in_stock' => (bool) $product->in_stock,
                'stock_quantity' => (int) $product->stock_quantity,
                'track_stock' => (bool) $product->track_stock,
                'image_url' => $product->image_url,
                'category' => $product->category ? [
                    'id' => (int) $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
                'brand' => $product->brand ? [
                    'id' => (int) $product->brand->id,
                    'name' => $product->brand->name,
                    'slug' => $product->brand->slug,
                ] : null,
                'added_at' => $row->created_at,
            ];
        })->filter()->values();

        return Inertia::render('Store/Wishlist', [
            'products' => $items,
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->status === 'active', 404);

        $query = DB::table('wishlists')
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id);

        if ($query->exists()) {
            $query->delete();
            return back()->with('success', 'Product removed from your wishlist.');
        }

        DB::table('wishlists')->insert([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Product added to your wishlist.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        DB::table('wishlists')
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return back()->with('success', 'Product removed from your wishlist.');
    }
}
