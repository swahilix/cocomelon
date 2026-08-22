<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $suppliers = Supplier::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', $search)
                        ->orWhere('contact_person', 'like', $search)
                        ->orWhere('phone', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('tax_number', 'like', $search);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => $this->summary($supplier));

        return Inertia::render('Admin/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only('search', 'status'),
            'summary' => [
                'total' => Supplier::count(),
                'active' => Supplier::active()->count(),
                'total_purchases' => (float) DB::table('stock_purchases')->whereNotNull('supplier_id')->sum('total'),
                'outstanding' => Supplier::query()->get()->sum(fn (Supplier $supplier) => $this->balance($supplier)),
            ],
        ]);
    }

    public function show(Supplier $supplier): Response
    {
        $purchases = DB::table('stock_purchases')
            ->where('supplier_id', $supplier->id)
            ->latest('purchase_date')
            ->latest('id')
            ->limit(50)
            ->get();

        $products = DB::table('stock_purchase_items')
            ->join('stock_purchases', 'stock_purchases.id', '=', 'stock_purchase_items.stock_purchase_id')
            ->join('products', 'products.id', '=', 'stock_purchase_items.product_id')
            ->where('stock_purchases.supplier_id', $supplier->id)
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->selectRaw('products.id, products.name, products.sku, SUM(stock_purchase_items.quantity) as quantity_supplied, SUM(stock_purchase_items.line_total) as purchase_value, MAX(stock_purchase_items.created_at) as last_supplied_at')
            ->orderByDesc('quantity_supplied')
            ->limit(30)
            ->get();

        $paidPurchases = $purchases->where('payment_method', '!=', 'credit');

        return Inertia::render('Admin/Suppliers/Show', [
            'supplier' => [
                ...$supplier->toArray(),
                'total_purchases' => (float) $purchases->sum('total'),
                'paid_amount' => (float) $paidPurchases->sum('total'),
                'outstanding_amount' => $this->balance($supplier),
                'purchase_count' => $purchases->count(),
            ],
            'purchases' => $purchases,
            'products' => $products,
            'payments' => $paidPurchases->values()->map(fn ($purchase) => [
                'id' => $purchase->id,
                'reference' => $purchase->reference,
                'date' => $purchase->purchase_date,
                'amount' => (float) $purchase->total,
                'method' => $purchase->payment_method,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;
        Supplier::create($data);

        return back()->with('success', 'Supplier created successfully.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));

        return back()->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => false]);

        return back()->with('success', 'Supplier marked inactive. Existing purchase history was preserved.');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:100', Rule::unique('suppliers', 'tax_number')->ignore($supplier)],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function summary(Supplier $supplier): array
    {
        $purchaseQuery = DB::table('stock_purchases')->where('supplier_id', $supplier->id);
        $total = (float) (clone $purchaseQuery)->sum('total');
        $paid = (float) (clone $purchaseQuery)->where('payment_method', '!=', 'credit')->sum('total');

        return [
            ...$supplier->toArray(),
            'purchase_count' => (clone $purchaseQuery)->count(),
            'total_purchases' => $total,
            'paid_amount' => $paid,
            'outstanding_amount' => (float) $supplier->opening_balance + ($total - $paid),
            'last_purchase_at' => (clone $purchaseQuery)->max('purchase_date'),
        ];
    }

    private function balance(Supplier $supplier): float
    {
        $purchases = DB::table('stock_purchases')->where('supplier_id', $supplier->id);
        $total = (float) (clone $purchases)->sum('total');
        $paid = (float) (clone $purchases)->where('payment_method', '!=', 'credit')->sum('total');

        return (float) $supplier->opening_balance + ($total - $paid);
    }
}
