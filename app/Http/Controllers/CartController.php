<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(CartService $cart): Response
    {
        $summary = $cart->summary();
        $cartProductIds = collect($summary['items'])->pluck('product.id')->filter()->values();

        return Inertia::render('Store/Cart', [
            'cart' => $summary,
            'recommended' => Product::query()
                ->with(['brand', 'category'])
                ->active()
                ->when($cartProductIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $cartProductIds))
                ->where(fn ($query) => $query->where('featured', true)->orWhere('stock_quantity', '>', 0)->orWhere('track_stock', false))
                ->orderByDesc('featured')
                ->orderBy('sort_order')
                ->latest()
                ->take(4)
                ->get(),
        ]);
    }

    public function store(Request $request, Product $product, CartService $cart): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:99']]);
        $cart->add($product, (int) ($data['quantity'] ?? 1));

        return back()->with('success', 'Product added to cart.');
    }

    public function update(Request $request, Product $product, CartService $cart): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);
        $cart->update($product, (int) $data['quantity']);

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(Product $product, CartService $cart): RedirectResponse
    {
        $cart->remove($product);

        return back()->with('success', 'Product removed.');
    }

    public function clear(CartService $cart): RedirectResponse
    {
        $cart->clear();

        return back()->with('success', 'Cart cleared.');
    }
}
