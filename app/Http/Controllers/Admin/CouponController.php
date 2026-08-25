<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {
        $coupons = Coupon::query()
            ->with(['categories:id,name', 'products:id,name,sku', 'brands:id,name'])
            ->latest()
            ->get()
            ->map(fn (Coupon $coupon): array => [
                'id' => $coupon->id,
                'name' => $coupon->name,
                'code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => (float) $coupon->discount_value,
                'applies_to' => $coupon->applies_to,
                'minimum_order_amount' => $coupon->minimum_order_amount !== null ? (float) $coupon->minimum_order_amount : null,
                'starts_at' => $coupon->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $coupon->ends_at?->format('Y-m-d\TH:i'),
                'is_active' => $coupon->is_active,
                'usage_count' => $coupon->usage_count,
                'categories' => $coupon->categories,
                'products' => $coupon->products,
                'brands' => $coupon->brands,
                'status' => $this->status($coupon),
            ]);

        return Inertia::render('Admin/Coupons', [
            'coupons' => $coupons,
            'categories' => Category::query()
                ->where('type', 'product')
                ->orderBy('name')
                ->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'category_id', 'brand_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $coupon = Coupon::create($this->attributes($data));
        $this->syncTargets($coupon, $data);

        return back()->with('success', 'Coupon created successfully.');
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $data = $this->validated($request, $coupon);
        $coupon->update($this->attributes($data));
        $this->syncTargets($coupon, $data);

        return back()->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }

    protected function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:64', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'gt:0'],
            'applies_to' => ['required', Rule::in(['all', 'category', 'product', 'brand', 'order_amount'])],
            'minimum_order_amount' => ['nullable', 'numeric', 'gt:0', 'required_if:applies_to,order_amount'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['required', 'boolean'],
            'category_ids' => ['nullable', 'array', 'required_if:applies_to,category'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'product_ids' => ['nullable', 'array', 'required_if:applies_to,product'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'brand_ids' => ['nullable', 'array', 'required_if:applies_to,brand'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
        ]);

        if ($data['discount_type'] === 'percentage' && (float) $data['discount_value'] > 100) {
            throw ValidationException::withMessages([
                'discount_value' => 'Percentage discounts cannot be greater than 100%.',
            ]);
        }

        return $data;
    }

    protected function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'code' => $data['code'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'applies_to' => $data['applies_to'],
            'minimum_order_amount' => $data['applies_to'] === 'order_amount' ? $data['minimum_order_amount'] : null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'is_active' => $data['is_active'],
        ];
    }

    protected function syncTargets(Coupon $coupon, array $data): void
    {
        $coupon->categories()->sync($data['applies_to'] === 'category' ? ($data['category_ids'] ?? []) : []);
        $coupon->products()->sync($data['applies_to'] === 'product' ? ($data['product_ids'] ?? []) : []);
        $coupon->brands()->sync($data['applies_to'] === 'brand' ? ($data['brand_ids'] ?? []) : []);
    }

    protected function status(Coupon $coupon): string
    {
        if (! $coupon->is_active) {
            return 'Inactive';
        }

        if (now()->lt($coupon->starts_at)) {
            return 'Scheduled';
        }

        if (now()->gt($coupon->ends_at)) {
            return 'Expired';
        }

        return 'Active';
    }
}
