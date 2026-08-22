<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->select([
                'id', 'category_id', 'brand_id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold',
                'track_stock', 'price', 'sale_price', 'primary_image',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product): array {
                $lastMovement = DB::table('stock_movements')->where('product_id', $product->id)->latest('id')->first();
                $lastCost = (float) (DB::table('stock_purchase_items')->where('product_id', $product->id)->latest('id')->value('unit_cost') ?? 0);
                $status = ! $product->track_stock
                    ? 'not_tracked'
                    : ($product->stock_quantity <= 0
                        ? 'out_of_stock'
                        : ($product->stock_quantity <= $product->low_stock_threshold ? 'low_stock' : 'in_stock'));

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'brand' => $product->brand?->name,
                    'stock_quantity' => $product->stock_quantity,
                    'low_stock_threshold' => $product->low_stock_threshold,
                    'track_stock' => $product->track_stock,
                    'price' => $product->current_price,
                    'image_url' => $product->image_url,
                    'stock_status' => $status,
                    'last_cost' => $lastCost,
                    'stock_value' => $product->track_stock ? $lastCost * $product->stock_quantity : 0,
                    'last_movement' => $lastMovement ? [
                        'type' => $lastMovement->type,
                        'quantity' => $lastMovement->quantity,
                        'balance_after' => $lastMovement->balance_after,
                        'created_at' => $lastMovement->created_at,
                    ] : null,
                    'has_movements' => (bool) $lastMovement,
                ];
            });

        $stats = [
            'stock_units' => Product::query()->where('track_stock', true)->sum('stock_quantity'),
            'low_stock' => Product::query()->where('track_stock', true)->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->where('stock_quantity', '>', 0)->count(),
            'out_of_stock' => Product::query()->where('track_stock', true)->where('stock_quantity', '<=', 0)->count(),
            'stock_value' => $products->sum('stock_value'),
            'today_sales' => (float) DB::table('pos_sales')->whereDate('created_at', $today)->sum('total'),
            'month_sales' => (float) DB::table('pos_sales')->whereDate('created_at', '>=', $monthStart)->sum('total'),
            'month_purchases' => (float) DB::table('stock_purchases')->where('purchase_date', '>=', $monthStart)->sum('total'),
            'month_expenses' => (float) DB::table('expenses')->where('expense_date', '>=', $monthStart)->sum('amount'),
        ];

        return Inertia::render('Admin/Stock/Index', [
            'products' => $products,
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name', 'contact_person', 'phone']),
            'stats' => $stats,
            'categories' => $products->pluck('category')->filter()->unique()->sort()->values(),
            'brands' => $products->pluck('brand')->filter()->unique()->sort()->values(),
            'purchases' => DB::table('stock_purchases')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'stock_purchases.supplier_id')
                ->select('stock_purchases.*', DB::raw('COALESCE(suppliers.name, stock_purchases.supplier_name) as display_supplier_name'))
                ->latest('stock_purchases.id')->limit(20)->get(),
            'sales' => DB::table('pos_sales')->latest('id')->limit(20)->get(),
            'expenses' => DB::table('expenses')->latest('id')->limit(20)->get(),
            'movements' => DB::table('stock_movements')
                ->join('products', 'products.id', '=', 'stock_movements.product_id')
                ->leftJoin('users', 'users.id', '=', 'stock_movements.created_by')
                ->select('stock_movements.*', 'products.name as product_name', 'products.sku', 'users.name as performed_by')
                ->latest('stock_movements.id')->limit(50)->get(),
        ]);
    }

    public function purchase(Request $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id', 'required_without:one_time_supplier_name'],
            'one_time_supplier_name' => ['nullable', 'string', 'max:255', 'required_without:supplier_id'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['required', 'date'],
            'additional_cost' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $supplier = ! empty($validated['supplier_id']) ? Supplier::active()->findOrFail($validated['supplier_id']) : null;
        $supplierName = $supplier?->name ?: trim((string) ($validated['one_time_supplier_name'] ?? ''));

        DB::transaction(function () use ($validated, $request, $inventory, $supplier, $supplierName): void {
            $reference = $this->reference('PUR');
            $subtotal = collect($validated['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_cost']);
            $additional = (float) ($validated['additional_cost'] ?? 0);
            $purchaseId = DB::table('stock_purchases')->insertGetId([
                'reference' => $reference,
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplierName,
                'invoice_number' => $validated['invoice_number'] ?? null,
                'purchase_date' => $validated['purchase_date'],
                'subtotal' => $subtotal,
                'additional_cost' => $additional,
                'total' => $subtotal + $additional,
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::query()->findOrFail($item['product_id']);
                $lineTotal = $item['quantity'] * $item['unit_cost'];

                DB::table('stock_purchase_items')->insert([
                    'stock_purchase_id' => $purchaseId,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $lineTotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $inventory->increase(
                    $product,
                    $item['quantity'],
                    'purchase',
                    $reference,
                    (float) $item['unit_cost'],
                    $supplierName,
                    $request->user()?->id,
                    'stock_purchase',
                    $purchaseId,
                    'Purchase received',
                );
            }
        });

        return back()->with('success', 'Purchase recorded and stock updated successfully.');
    }

    public function adjustment(Request $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'mode' => ['required', 'in:opening,adjustment'],
            'target_quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $inventory): void {
                if ($validated['mode'] === 'opening') {
                    $inventory->openingBalance($validated['product_id'], $validated['target_quantity'], $validated['notes'] ?? null, $request->user()?->id);
                    return;
                }

                $inventory->adjust($validated['product_id'], $validated['target_quantity'], $validated['reason'], $validated['notes'] ?? null, $request->user()?->id);
            });
        } catch (Throwable $exception) {
            return back()->withErrors(['adjustment' => $exception->getMessage()]);
        }

        return back()->with('success', 'Stock adjustment posted successfully.');
    }

    public function expense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:40'],
            'payee' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::table('expenses')->insert([
            ...$validated,
            'reference' => $this->reference('EXP'),
            'created_by' => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function pos(Request $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $inventory): void {
                $reference = $this->reference('POS');
                $subtotal = collect($validated['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_price']);
                $discount = min((float) ($validated['discount'] ?? 0), $subtotal);
                $total = $subtotal - $discount;
                $paid = (float) $validated['amount_paid'];

                if ($paid < $total) {
                    throw new RuntimeException('Amount paid cannot be less than the sale total.');
                }

                $saleId = DB::table('pos_sales')->insertGetId([
                    'reference' => $reference,
                    'customer_name' => $validated['customer_name'] ?? null,
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'amount_paid' => $paid,
                    'change_due' => $paid - $total,
                    'payment_method' => $validated['payment_method'],
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($validated['items'] as $item) {
                    $product = Product::query()->findOrFail($item['product_id']);
                    $lineTotal = $item['quantity'] * $item['unit_price'];
                    $lastCost = (float) (DB::table('stock_purchase_items')->where('product_id', $product->id)->latest('id')->value('unit_cost') ?? 0);

                    DB::table('pos_sale_items')->insert([
                        'pos_sale_id' => $saleId,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'unit_cost' => $lastCost,
                        'line_total' => $lineTotal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $inventory->decrease($product, $item['quantity'], 'pos_sale', $reference, $lastCost, $validated['customer_name'] ?? 'Walk-in customer', $request->user()?->id, 'pos_sale', $saleId, 'POS sale');
                }
            });
        } catch (Throwable $exception) {
            return back()->withErrors(['pos' => $exception->getMessage()]);
        }

        return back()->with('success', 'POS sale completed and stock updated successfully.');
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
    }
}
