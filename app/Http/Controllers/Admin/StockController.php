<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
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
    public function index(): Response
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $products = Product::query()
            ->select(['id', 'name', 'sku', 'stock_quantity', 'track_stock', 'price', 'sale_price', 'primary_image'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock_quantity' => $product->stock_quantity,
                'track_stock' => $product->track_stock,
                'price' => $product->current_price,
                'image_url' => $product->image_url,
            ]);

        $stats = [
            'stock_units' => Product::query()->where('track_stock', true)->sum('stock_quantity'),
            'low_stock' => Product::query()->where('track_stock', true)->where('stock_quantity', '<=', 5)->count(),
            'today_sales' => (float) DB::table('pos_sales')->whereDate('created_at', $today)->sum('total'),
            'month_sales' => (float) DB::table('pos_sales')->whereDate('created_at', '>=', $monthStart)->sum('total'),
            'month_purchases' => (float) DB::table('stock_purchases')->where('purchase_date', '>=', $monthStart)->sum('total'),
            'month_expenses' => (float) DB::table('expenses')->where('expense_date', '>=', $monthStart)->sum('amount'),
        ];

        return Inertia::render('Admin/Stock/Index', [
            'products' => $products,
            'stats' => $stats,
            'purchases' => DB::table('stock_purchases')->latest('id')->limit(20)->get(),
            'sales' => DB::table('pos_sales')->latest('id')->limit(20)->get(),
            'expenses' => DB::table('expenses')->latest('id')->limit(20)->get(),
            'movements' => DB::table('stock_movements')
                ->join('products', 'products.id', '=', 'stock_movements.product_id')
                ->select('stock_movements.*', 'products.name as product_name', 'products.sku')
                ->latest('stock_movements.id')->limit(30)->get(),
        ]);
    }

    public function purchase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_name' => ['nullable', 'string', 'max:255'],
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

        DB::transaction(function () use ($validated, $request): void {
            $reference = $this->reference('PUR');
            $subtotal = collect($validated['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_cost']);
            $additional = (float) ($validated['additional_cost'] ?? 0);
            $purchaseId = DB::table('stock_purchases')->insertGetId([
                'reference' => $reference,
                'supplier_name' => $validated['supplier_name'] ?? null,
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
                $product = Product::query()->lockForUpdate()->findOrFail($item['product_id']);
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

                if ($product->track_stock) {
                    $product->increment('stock_quantity', $item['quantity']);
                    $product->refresh();
                }

                DB::table('stock_movements')->insert([
                    'product_id' => $product->id,
                    'direction' => 'in',
                    'type' => 'purchase',
                    'quantity' => $item['quantity'],
                    'balance_after' => $product->track_stock ? $product->stock_quantity : null,
                    'unit_cost' => $item['unit_cost'],
                    'reference' => $reference,
                    'notes' => $validated['supplier_name'] ?? null,
                    'created_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Purchase recorded and stock updated successfully.');
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

    public function pos(Request $request): RedirectResponse
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
            DB::transaction(function () use ($validated, $request): void {
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
                    $product = Product::query()->lockForUpdate()->findOrFail($item['product_id']);

                    if ($product->track_stock && $product->stock_quantity < $item['quantity']) {
                        throw new RuntimeException("Not enough stock for {$product->name}. Available: {$product->stock_quantity}.");
                    }

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

                    if ($product->track_stock) {
                        $product->decrement('stock_quantity', $item['quantity']);
                        $product->refresh();
                    }

                    DB::table('stock_movements')->insert([
                        'product_id' => $product->id,
                        'direction' => 'out',
                        'type' => 'pos_sale',
                        'quantity' => -1 * $item['quantity'],
                        'balance_after' => $product->track_stock ? $product->stock_quantity : null,
                        'unit_cost' => $lastCost,
                        'reference' => $reference,
                        'notes' => $validated['customer_name'] ?? null,
                        'created_by' => $request->user()?->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
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
