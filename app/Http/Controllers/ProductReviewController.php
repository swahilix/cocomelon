<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->status === 'active', 404);

        $user = $request->user();

        $eligibleOrder = Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereHas('items', fn ($items) => $items->where('product_id', $product->id))
            ->latest('id')
            ->first();

        if (! $eligibleOrder) {
            return back()->with('error', 'Only customers with a completed purchase of this product can leave a verified review.');
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        ProductReview::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'user_id' => $user->id,
            ],
            [
                'order_id' => $eligibleOrder->id,
                'reviewer_name' => trim((string) ($user->name ?: 'Customer')),
                'rating' => (int) $validated['rating'],
                'title' => filled($validated['title'] ?? null) ? trim((string) $validated['title']) : null,
                'body' => trim((string) $validated['body']),
                'status' => 'pending',
                'is_verified_purchase' => true,
                'approved_at' => null,
                'moderated_by' => null,
                'moderation_note' => null,
            ],
        );

        return back()->with('success', 'Thank you. Your verified review was submitted for approval.');
    }
}
