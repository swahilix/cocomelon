<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class HistoricalDataController extends Controller
{
    public function index(): Response
    {
        $state = DB::table('historical_import_states')->where('id', 1)->first();

        $products = Product::query()
            ->with(['brand:id,name', 'category:id,name'])
            ->select([
                'id', 'brand_id', 'category_id', 'name', 'sku', 'primary_image', 'price', 'sale_price',
                'sale_starts_at', 'sale_ends_at', 'average_cost', 'last_purchase_cost', 'stock_quantity', 'track_stock',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'brand' => $product->brand?->name,
                'category' => $product->category?->name,
                'image_url' => $product->image_url,
                'price' => (float) $product->current_price,
                'average_cost' => (float) $product->average_cost,
                'last_purchase_cost' => (float) $product->last_purchase_cost,
                'stock_quantity' => (int) $product->stock_quantity,
                'track_stock' => (bool) $product->track_stock,
            ]);

        $historicalPurchases = DB::table('stock_purchases')
            ->where('is_historical', true)
            ->leftJoin('suppliers', 'suppliers.id', '=', 'stock_purchases.supplier_id')
            ->select(
                'stock_purchases.id', 'stock_purchases.reference', 'stock_purchases.purchase_date', 'stock_purchases.total',
                'stock_purchases.amount_paid', 'stock_purchases.payment_status', 'stock_purchases.invoice_number',
                DB::raw('COALESCE(suppliers.name, stock_purchases.supplier_name) as supplier_name')
            )
            ->orderByDesc('stock_purchases.purchase_date')
            ->orderByDesc('stock_purchases.id')
            ->limit(100)
            ->get();

        $historicalSales = DB::table('pos_sales')
            ->where('is_historical', true)
            ->select('id', 'reference', 'customer_name', 'total', 'amount_paid', 'payment_method', 'status', 'created_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return Inertia::render('Admin/Historical/Index', [
            'products' => $products,
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => DB::table('payment_methods')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_cash']),
            'state' => [
                'purchases_confirmed' => (bool) ($state?->purchases_confirmed_at),
                'purchases_confirmed_at' => $state?->purchases_confirmed_at,
                'completed' => (bool) ($state?->completed_at),
                'completed_at' => $state?->completed_at,
            ],
            'summary' => [
                'purchase_count' => DB::table('stock_purchases')->where('is_historical', true)->count(),
                'purchase_value' => (float) DB::table('stock_purchases')->where('is_historical', true)->where('status', '!=', 'cancelled')->sum('total'),
                'sale_count' => DB::table('pos_sales')->where('is_historical', true)->where('status', '!=', 'voided')->count(),
                'sale_value' => (float) DB::table('pos_sales')->where('is_historical', true)->where('status', '!=', 'voided')->sum('total'),
            ],
            'historicalPurchases' => $historicalPurchases,
            'historicalSales' => $historicalSales,
        ]);
    }

    public function storePurchase(Request $request): RedirectResponse
    {
        $this->ensureSetupOpenForPurchases();

        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id', 'required_without:one_time_supplier_name'],
            'one_time_supplier_name' => ['nullable', 'string', 'max:255', 'required_without:supplier_id'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'additional_cost' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:120'],
            'payment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $supplier = ! empty($validated['supplier_id']) ? Supplier::active()->findOrFail($validated['supplier_id']) : null;
        $supplierName = $supplier?->name ?: trim((string) ($validated['one_time_supplier_name'] ?? ''));

        try {
            DB::transaction(function () use ($validated, $request, $supplier, $supplierName): void {
                $subtotal = (float) collect($validated['items'])->sum(fn ($item) => (int) $item['quantity'] * (float) $item['unit_cost']);
                $additional = (float) ($validated['additional_cost'] ?? 0);
                $discount = min((float) ($validated['discount'] ?? 0), $subtotal + $additional);
                $tax = (float) ($validated['tax'] ?? 0);
                $total = max(0, $subtotal + $additional - $discount + $tax);
                $paid = (float) ($validated['amount_paid'] ?? $total);

                if ($paid > $total + 0.001) {
                    throw ValidationException::withMessages(['amount_paid' => 'Amount paid cannot be greater than the purchase total.']);
                }

                $reference = $this->reference('HPUR');
                $receiptReference = $this->reference('HRCV');
                $purchaseDate = Carbon::parse($validated['purchase_date'])->startOfDay();
                $purchaseId = DB::table('stock_purchases')->insertGetId([
                    'reference' => $reference,
                    'supplier_id' => $supplier?->id,
                    'supplier_name' => $supplierName,
                    'invoice_number' => $validated['invoice_number'] ?? null,
                    'purchase_date' => $purchaseDate->toDateString(),
                    'status' => 'received',
                    'expected_date' => $purchaseDate->toDateString(),
                    'received_date' => $purchaseDate->toDateString(),
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'additional_cost' => $additional,
                    'total' => $total,
                    'amount_paid' => $paid,
                    'balance_due' => max(0, $total - $paid),
                    'payment_status' => $paid <= 0 ? 'unpaid' : ($paid + 0.001 >= $total ? 'paid' : 'partial'),
                    'is_historical' => true,
                    'historical_imported_at' => now(),
                    'payment_method' => $validated['payment_method'],
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $receiptId = DB::table('stock_purchase_receipts')->insertGetId([
                    'stock_purchase_id' => $purchaseId,
                    'reference' => $receiptReference,
                    'received_date' => $purchaseDate->toDateString(),
                    'notes' => 'Historical receipt imported without changing current physical stock.',
                    'created_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($validated['items'] as $item) {
                    $quantity = (int) $item['quantity'];
                    $unitCost = (float) $item['unit_cost'];
                    $product = Product::query()->findOrFail($item['product_id']);
                    $lineTotal = $quantity * $unitCost;

                    $purchaseItemId = DB::table('stock_purchase_items')->insertGetId([
                        'stock_purchase_id' => $purchaseId,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'quantity_received' => $quantity,
                        'quantity_returned' => 0,
                        'unit_cost' => $unitCost,
                        'discount' => 0,
                        'tax' => 0,
                        'line_total' => $lineTotal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('stock_purchase_receipt_items')->insert([
                        'stock_purchase_receipt_id' => $receiptId,
                        'stock_purchase_item_id' => $purchaseItemId,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->historicalMovement(
                        $product->id,
                        $quantity,
                        'historical_purchase',
                        $reference,
                        $unitCost,
                        'stock_purchase',
                        $purchaseId,
                        'Historical purchase import',
                        $request->user()?->id,
                        $purchaseDate->copy()->setTime(12, 0),
                    );
                }

                if ($paid > 0) {
                    DB::table('stock_purchase_payments')->insert([
                        'stock_purchase_id' => $purchaseId,
                        'supplier_id' => $supplier?->id,
                        'reference' => $this->reference('HPAY'),
                        'payment_date' => $validated['payment_date'] ?? $purchaseDate->toDateString(),
                        'amount' => $paid,
                        'payment_method' => $validated['payment_method'],
                        'transaction_reference' => $validated['transaction_reference'] ?? null,
                        'notes' => 'Historical purchase payment import.',
                        'created_by' => $request->user()?->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return back()->withErrors(['purchase' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Historical purchase saved. Current stock was not changed.');
    }

    public function confirmPurchases(Request $request): RedirectResponse
    {
        DB::table('historical_import_states')->updateOrInsert(
            ['id' => 1],
            [
                'purchases_confirmed_at' => now(),
                'purchases_confirmed_by' => $request->user()?->id,
                'completed_at' => null,
                'completed_by' => null,
                'created_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
                'updated_at' => now(),
            ],
        );

        return back()->with('success', 'Historical purchases confirmed. Historical sales entry is now unlocked.');
    }

    public function reopenPurchases(Request $request): RedirectResponse
    {
        DB::table('historical_import_states')->updateOrInsert(
            ['id' => 1],
            [
                'purchases_confirmed_at' => null,
                'purchases_confirmed_by' => null,
                'completed_at' => null,
                'completed_by' => null,
                'created_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
                'updated_at' => now(),
            ],
        );

        return back()->with('success', 'Purchases reopened. Sales entry is locked until purchases are confirmed again.');
    }

    public function storeSale(Request $request): RedirectResponse
    {
        $state = DB::table('historical_import_states')->where('id', 1)->first();
        if (! $state?->purchases_confirmed_at) {
            return back()->withErrors(['sale' => 'Confirm that all historical purchases are entered before adding historical sales.']);
        }
        if ($state?->completed_at) {
            return back()->withErrors(['sale' => 'Historical data setup is marked complete. Reopen purchases if more records must be entered.']);
        }

        $validated = $request->validate([
            'sale_at' => ['required', 'date', 'before_or_equal:now'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:60'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request): void {
                $saleAt = Carbon::parse($validated['sale_at']);
                $paymentMethod = DB::table('payment_methods')->where('id', $validated['payment_method_id'])->first();
                if (! $paymentMethod) throw new RuntimeException('Payment method was not found.');

                $subtotal = (float) collect($validated['items'])->sum(fn ($item) => (int) $item['quantity'] * (float) $item['unit_price']);
                $discount = min((float) ($validated['discount'] ?? 0), $subtotal);
                $total = max(0, $subtotal - $discount);
                $paid = (float) ($validated['amount_paid'] ?? $total);
                $reference = $this->reference('HPOS');

                $saleId = DB::table('pos_sales')->insertGetId([
                    'cash_register_session_id' => null,
                    'payment_method_id' => $paymentMethod->id,
                    'reference' => $reference,
                    'customer_id' => null,
                    'customer_name' => $validated['customer_name'] ?? null,
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'amount_paid' => $paid,
                    'change_due' => max(0, $paid - $total),
                    'payment_method' => $paymentMethod->name,
                    'payment_reference' => $validated['payment_reference'] ?? null,
                    'status' => 'completed',
                    'is_historical' => true,
                    'historical_imported_at' => now(),
                    'refunded_amount' => 0,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                    'created_at' => $saleAt,
                    'updated_at' => now(),
                ]);

                foreach ($validated['items'] as $index => $item) {
                    $product = Product::query()->findOrFail($item['product_id']);
                    $quantity = (int) $item['quantity'];
                    $unitPrice = (float) $item['unit_price'];
                    $unitCost = isset($item['unit_cost']) && $item['unit_cost'] !== null && $item['unit_cost'] !== ''
                        ? (float) $item['unit_cost']
                        : $this->historicalUnitCost($product, $saleAt);

                    if ($unitCost <= 0) {
                        throw ValidationException::withMessages([
                            "items.{$index}.unit_cost" => "No historical cost could be calculated for {$product->name}. Enter its unit cost at the time of sale.",
                        ]);
                    }

                    $saleItemId = DB::table('pos_sale_items')->insertGetId([
                        'pos_sale_id' => $saleId,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => $quantity,
                        'quantity_returned' => 0,
                        'unit_price' => $unitPrice,
                        'unit_cost' => $unitCost,
                        'line_total' => $quantity * $unitPrice,
                        'created_at' => $saleAt,
                        'updated_at' => now(),
                    ]);

                    $this->historicalMovement(
                        $product->id,
                        -$quantity,
                        'historical_sale',
                        $reference,
                        $unitCost,
                        'pos_sale',
                        $saleId,
                        'Historical sale import',
                        $request->user()?->id,
                        $saleAt,
                        "Historical sale item #{$saleItemId}; current stock unchanged.",
                    );
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return back()->withErrors(['sale' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Historical sale saved and included in reports. Current stock and cash register were not changed.');
    }

    public function complete(Request $request): RedirectResponse
    {
        $state = DB::table('historical_import_states')->where('id', 1)->first();
        if (! $state?->purchases_confirmed_at) {
            return back()->withErrors(['setup' => 'Confirm historical purchases before completing the historical data setup.']);
        }

        DB::table('historical_import_states')->updateOrInsert(
            ['id' => 1],
            [
                'completed_at' => now(),
                'completed_by' => $request->user()?->id,
                'updated_at' => now(),
            ],
        );

        return back()->with('success', 'Historical data setup marked complete. The imported records remain part of all reports.');
    }

    public function destroyPurchase(int $purchase): RedirectResponse
    {
        $this->ensureSetupOpenForPurchases();
        $record = DB::table('stock_purchases')->where('id', $purchase)->where('is_historical', true)->first();
        abort_unless($record, 404);

        DB::transaction(function () use ($purchase): void {
            $receiptIds = DB::table('stock_purchase_receipts')->where('stock_purchase_id', $purchase)->pluck('id');
            if ($receiptIds->isNotEmpty()) {
                DB::table('stock_purchase_receipt_items')->whereIn('stock_purchase_receipt_id', $receiptIds)->delete();
            }
            DB::table('stock_purchase_receipts')->where('stock_purchase_id', $purchase)->delete();
            DB::table('stock_purchase_payments')->where('stock_purchase_id', $purchase)->delete();
            DB::table('stock_purchase_return_items')->whereIn('stock_purchase_return_id', DB::table('stock_purchase_returns')->where('stock_purchase_id', $purchase)->pluck('id'))->delete();
            DB::table('stock_purchase_returns')->where('stock_purchase_id', $purchase)->delete();
            DB::table('stock_movements')->where('is_historical', true)->where('source_type', 'stock_purchase')->where('source_id', $purchase)->delete();
            DB::table('stock_purchase_items')->where('stock_purchase_id', $purchase)->delete();
            DB::table('stock_purchases')->where('id', $purchase)->delete();
        });

        return back()->with('success', 'Historical purchase removed. Current stock was unchanged.');
    }

    public function destroySale(int $sale): RedirectResponse
    {
        $state = DB::table('historical_import_states')->where('id', 1)->first();
        if ($state?->completed_at) {
            return back()->withErrors(['sale' => 'Reopen the historical setup before removing imported sales.']);
        }

        $record = DB::table('pos_sales')->where('id', $sale)->where('is_historical', true)->first();
        abort_unless($record, 404);

        DB::transaction(function () use ($sale): void {
            $returnIds = DB::table('pos_sale_returns')->where('pos_sale_id', $sale)->pluck('id');
            if ($returnIds->isNotEmpty()) {
                DB::table('pos_sale_return_items')->whereIn('pos_sale_return_id', $returnIds)->delete();
            }
            DB::table('pos_sale_returns')->where('pos_sale_id', $sale)->delete();
            DB::table('stock_movements')->where('is_historical', true)->where('source_type', 'pos_sale')->where('source_id', $sale)->delete();
            DB::table('pos_sale_items')->where('pos_sale_id', $sale)->delete();
            DB::table('pos_sales')->where('id', $sale)->delete();
        });

        return back()->with('success', 'Historical sale removed. Current stock and cash register were unchanged.');
    }

    private function ensureSetupOpenForPurchases(): void
    {
        $state = DB::table('historical_import_states')->where('id', 1)->first();
        if ($state?->purchases_confirmed_at || $state?->completed_at) {
            throw ValidationException::withMessages([
                'purchase' => 'Historical purchases are locked. Reopen purchases before adding or removing purchase records.',
            ]);
        }
    }

    private function historicalUnitCost(Product $product, Carbon $saleAt): float
    {
        $rows = DB::table('stock_purchase_items')
            ->join('stock_purchases', 'stock_purchases.id', '=', 'stock_purchase_items.stock_purchase_id')
            ->where('stock_purchase_items.product_id', $product->id)
            ->where('stock_purchases.status', '!=', 'cancelled')
            ->whereDate('stock_purchases.purchase_date', '<=', $saleAt->toDateString())
            ->where('stock_purchase_items.quantity_received', '>', 0)
            ->get(['stock_purchase_items.quantity_received', 'stock_purchase_items.quantity_returned', 'stock_purchase_items.unit_cost']);

        $quantity = 0;
        $value = 0.0;
        foreach ($rows as $row) {
            $netQuantity = max(0, (int) $row->quantity_received - (int) $row->quantity_returned);
            $quantity += $netQuantity;
            $value += $netQuantity * (float) $row->unit_cost;
        }

        if ($quantity > 0) {
            return round($value / $quantity, 4);
        }

        return round(max((float) $product->average_cost, (float) $product->last_purchase_cost), 4);
    }

    private function historicalMovement(
        int $productId,
        int $quantity,
        string $type,
        string $reference,
        ?float $unitCost,
        string $sourceType,
        int $sourceId,
        string $reason,
        ?int $userId,
        Carbon $occurredAt,
        ?string $notes = null,
    ): void {
        DB::table('stock_movements')->insert([
            'product_id' => $productId,
            'direction' => $quantity > 0 ? 'in' : 'out',
            'type' => $type,
            'quantity' => $quantity,
            'affects_stock' => false,
            'is_historical' => true,
            'balance_before' => null,
            'balance_after' => null,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'unit_cost' => $unitCost,
            'reference' => $reference,
            'reason' => $reason,
            'reversal_of_id' => null,
            'notes' => $notes ?: 'Historical reference-only movement; current physical stock was not changed.',
            'created_by' => $userId,
            'created_at' => $occurredAt,
            'updated_at' => now(),
        ]);
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
    }
}
