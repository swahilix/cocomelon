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
    public function index(CartService $cart): Response { return Inertia::render('Store/Cart', ['cart' => $cart->summary()]); }
    public function store(Request $request, Product $product, CartService $cart): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['nullable','integer','min:1','max:99']]);
        $cart->add($product, (int)($data['quantity'] ?? 1));
        return back()->with('success', 'Product added to cart.');
    }
    public function update(Request $request, Product $product, CartService $cart): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required','integer','min:0','max:99']]);
        $cart->update($product, (int)$data['quantity']); return back()->with('success', 'Cart updated.');
    }
    public function destroy(Product $product, CartService $cart): RedirectResponse { $cart->remove($product); return back()->with('success', 'Product removed.'); }
}
