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
                'total_purchases' => (float) DB::table('stock_purchases')->whereNotNull('supplier_id')->where('status', '!=', 'cancelled')->sum('total'),
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
            ->limit(100)
            ->get();

        $products = DB::table('stock_purchase_items')
            ->join('stock_purchases', 'stock_purchases.id', '=', 'stock_purchase_items.stock_purchase_id')
            ->join('products', 'products.id', '=', 'stock_purchase_items.product_id')
            ->where('stock_purchases.supplier_id', $supplier->id)
            ->where('stock_purchases.status', '!=', 'cancelled')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->selectRaw('products.id, products.name, products.sku, SUM(stock_purchase_items.quantity_received) as quantity_supplied, SUM(stock_purchase_items.quantity_received * stock_purchase_items.unit_cost) as purchase_value, MAX(stock_purchase_items.updated_at) as last_supplied_at')
            ->orderByDesc('quantity_supplied')
            ->limit(30)
            ->get();

        $payments = DB::table('stock_purchase_payments')
            ->join('stock_purchases', 'stock_purchases.id', '=', 'stock_purchase_payments.stock_purchase_id')
            ->where('stock_purchase_payments.supplier_id', $supplier->id)
            ->select(
                'stock_purchase_payments.id',
                'stock_purchase_payments.reference',
                'stock_purchase_payments.payment_date as date',
                'stock_purchase_payments.amount',
                'stock_purchase_payments.payment_method as method',
                'stock_purchase_payments.transaction_reference',
                'stock_purchases.reference as purchase_reference',
                'stock_purchases.id as purchase_id'
            )
            ->latest('stock_purchase_payments.payment_date')
            ->latest('stock_purchase_payments.id')
            ->limit(100)
            ->get();

        $returns = DB::table('stock_purchase_returns')
            ->where('supplier_id', $supplier->id)
            ->latest('return_date')
            ->limit(50)
            ->get();

        $validPurchases = $purchases->where('status', '!=', 'cancelled');
        $paid = (float) $payments->sum('amount');
        $credits = (float) $returns->sum('credit_amount');

        return Inertia::render('Admin/Suppliers/Show', [
            'supplier' => [
                ...$supplier->toArray(),
                'total_purchases' => (float) $validPurchases->sum('total'),
                'paid_amount' => $paid,
                'return_credits' => $credits,
                'outstanding_amount' => $this->balance($supplier),
                'purchase_count' => $validPurchases->count(),
            ],
            'purchases' => $purchases,
            'products' => $products,
            'payments' => $payments,
            'returns' => $returns,
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
        $purchaseQuery = DB::table('stock_purchases')->where('supplier_id', $supplier->id)->where('status', '!=', 'cancelled');
        $total = (float) (clone $purchaseQuery)->sum('total');
        $paid = (float) DB::table('stock_purchase_payments')->where('supplier_id', $supplier->id)->sum('amount');
        $credits = (float) DB::table('stock_purchase_returns')->where('supplier_id', $supplier->id)->sum('credit_amount');

        return [
            ...$supplier->toArray(),
            'purchase_count' => (clone $purchaseQuery)->count(),
            'total_purchases' => $total,
            'paid_amount' => $paid,
            'return_credits' => $credits,
            'outstanding_amount' => max(0, (float) $supplier->opening_balance + $total - $paid - $credits),
            'last_purchase_at' => (clone $purchaseQuery)->max('purchase_date'),
        ];
    }

    private function balance(Supplier $supplier): float
    {
        $total = (float) DB::table('stock_purchases')
            ->where('supplier_id', $supplier->id)
            ->where('status', '!=', 'cancelled')
            ->sum('total');
        $paid = (float) DB::table('stock_purchase_payments')->where('supplier_id', $supplier->id)->sum('amount');
        $credits = (float) DB::table('stock_purchase_returns')->where('supplier_id', $supplier->id)->sum('credit_amount');

        return max(0, (float) $supplier->opening_balance + $total - $paid - $credits);
    }
}
