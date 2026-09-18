<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $reviews = ProductReview::query()
            ->with([
                'product:id,name,slug',
                'user:id,name,email,phone',
                'order:id,order_number',
            ])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviews,
            'status' => $status,
            'counts' => [
                'pending' => ProductReview::where('status', 'pending')->count(),
                'approved' => ProductReview::where('status', 'approved')->count(),
                'rejected' => ProductReview::where('status', 'rejected')->count(),
                'all' => ProductReview::count(),
            ],
        ]);
    }

    public function update(Request $request, ProductReview $productReview): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'moderation_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $productReview->update([
            'status' => $validated['status'],
            'moderation_note' => filled($validated['moderation_note'] ?? null)
                ? trim((string) $validated['moderation_note'])
                : null,
            'moderated_by' => $request->user()->id,
            'approved_at' => $validated['status'] === 'approved' ? now() : null,
        ]);

        return back()->with('success', 'Review status updated.');
    }
}
