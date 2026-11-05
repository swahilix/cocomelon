<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\PurchaseReceivingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class PurchaseController extends Controller
{
    public function show(int $purchase): Response
    {
        $record = DB::table('stock_purchases')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'stock_purchases.supplier_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'stock_purchases.created_by')
            ->where('stock_purchases.id', $purchase)
            ->select('stock_purchases.*', 'suppliers.name as saved_supplier_name', 'creators.name as created_by_name')
            ->first();

        abort_unless($record, 404);

        $items = DB::table('stock_purchase_items')
            ->join('products', 'products.id', '=', 'stock_purchase_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_purchase_items.product_variant_id')
            ->where('stock_purchase_items.stock_purchase_id', $purchase)
            ->select(
                'stock_purchase_items.*',
                'product_variants.label as variant_label',
                'product_variants.color as variant_color',
                'product_variants.storage as variant_storage',
                'product_variants.size as variant_size',
                'product_variants.sku as variant_sku',
                'products.name as product_name',
                'products.sku',
                'products.primary_image',
                'products.stock_quantity',
                'products.track_stock'
            )
            ->orderBy('stock_purchase_items.id')
            ->get()
            ->map(function ($item): object {
                $item->remaining_quantity = max(0, (int) $item->quantity - (int) $item->quantity_received);
                $item->returnable_quantity = max(0, (int) $item->quantity_received - (int) $item->quantity_returned);
                $item->variant_name = collect([$item->variant_label, $item->variant_color, $item->variant_storage, $item->variant_size])->filter()->implode(' · ') ?: null;
                return $item;
            });

        $receipts = DB::table('stock_purchase_receipts')
            ->leftJoin('users', 'users.id', '=', 'stock_purchase_receipts.created_by')
            ->where('stock_purchase_receipts.stock_purchase_id', $purchase)
            ->select('stock_purchase_receipts.*', 'users.name as received_by')
            ->latest('stock_purchase_receipts.id')
            ->get();

        $payments = DB::table('stock_purchase_payments')
            ->leftJoin('users', 'users.id', '=', 'stock_purchase_payments.created_by')
            ->where('stock_purchase_payments.stock_purchase_id', $purchase)
            ->select('stock_purchase_payments.*', 'users.name as recorded_by')
            ->latest('payment_date')->latest('stock_purchase_payments.id')
            ->get();

        $returns = DB::table('stock_purchase_returns')
            ->leftJoin('users', 'users.id', '=', 'stock_purchase_returns.created_by')
            ->where('stock_purchase_returns.stock_purchase_id', $purchase)
            ->select('stock_purchase_returns.*', 'users.name as recorded_by')
            ->latest('return_date')->latest('stock_purchase_returns.id')
            ->get();

        return Inertia::render('Admin/Stock/PurchaseShow', [
            'purchase' => $record,
            'items' => $items,
            'receipts' => $receipts,
            'payments' => $payments,
            'returns' => $returns,
            'returnCredit' => (float) $returns->sum('credit_amount'),
        ]);
    }

    public function update(Request $request, int $purchase): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,ordered'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = DB::table('stock_purchases')->where('id', $purchase)->first();
        abort_unless($record, 404);

        if (in_array($record->status, ['partially_received', 'received', 'cancelled'], true)) {
            return back()->withErrors(['purchase' => 'This purchase can no longer be moved back to Draft or Ordered.']);
        }

        DB::table('stock_purchases')->where('id', $purchase)->update([
            'status' => $validated['status'],
            'expected_date' => $validated['expected_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Purchase status updated successfully.');
    }

    public function receive(Request $request, int $purchase, PurchaseReceivingService $receiving): RedirectResponse
    {
        $validated = $request->validate([
            'received_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $receiving->receive($purchase, $validated['items'], $validated['received_date'], $validated['notes'] ?? null, $request->user()?->id);
        } catch (Throwable $exception) {
            return back()->withErrors(['receive' => $exception->getMessage()]);
        }

        return back()->with('success', 'Purchase receipt posted. Stock and selling prices were updated.');
    }

    public function payment(Request $request, int $purchase): RedirectResponse
    {
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank,card,other'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $purchase): void {
                $record = DB::table('stock_purchases')->where('id', $purchase)->lockForUpdate()->first();
                if (! $record) throw new RuntimeException('Purchase was not found.');
                if ($record->status === 'cancelled') throw new RuntimeException('Payments cannot be added to a cancelled purchase.');

                $this->refreshBalance($purchase);
                $record = DB::table('stock_purchases')->where('id', $purchase)->first();
                $amount = (float) $validated['amount'];
                if ($amount > (float) $record->balance_due + 0.001) {
                    throw new RuntimeException('Payment cannot be greater than the current purchase balance.');
                }

                DB::table('stock_purchase_payments')->insert([
                    'stock_purchase_id' => $purchase,
                    'supplier_id' => $record->supplier_id,
                    'reference' => $this->reference('PAY'),
                    'payment_date' => $validated['payment_date'],
                    'amount' => $amount,
                    'payment_method' => $validated['payment_method'],
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->refreshBalance($purchase);
            });
        } catch (Throwable $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('success', 'Purchase payment recorded successfully.');
    }

    public function returnItems(Request $request, int $purchase, InventoryService $inventory, PurchaseReceivingService $receiving): RedirectResponse
    {
        $validated = $request->validate([
            'return_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'items.*.credit_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $purchase, $inventory, $receiving): void {
                $record = DB::table('stock_purchases')->where('id', $purchase)->lockForUpdate()->first();
                if (! $record) throw new RuntimeException('Purchase was not found.');
                if ($record->status === 'cancelled') throw new RuntimeException('A cancelled purchase cannot post supplier returns.');

                $selected = collect($validated['items'])->filter(fn ($item) => (int) $item['quantity'] > 0);
                if ($selected->isEmpty()) throw new RuntimeException('Enter at least one quantity to return.');

                $returnReference = $this->reference('RET');
                $returnRows = [];
                $totalCredit = 0.0;

                foreach ($selected as $submitted) {
                    $item = DB::table('stock_purchase_items')
                        ->where('id', $submitted['purchase_item_id'])
                        ->where('stock_purchase_id', $purchase)
                        ->lockForUpdate()
                        ->first();
                    if (! $item) throw new RuntimeException('One of the purchase items was not found.');

                    $quantity = (int) $submitted['quantity'];
                    $returnable = (int) $item->quantity_received - (int) $item->quantity_returned;
                    if ($quantity > $returnable) {
                        throw new RuntimeException("Cannot return {$quantity} units. Only {$returnable} received units remain returnable.");
                    }

                    $lineCredit = isset($submitted['credit_amount']) && $submitted['credit_amount'] !== null
                        ? (float) $submitted['credit_amount']
                        : $quantity * (float) $item->unit_cost;
                    $totalCredit += $lineCredit;
                    $returnRows[] = [$item, $quantity, $lineCredit];
                }

                $returnId = DB::table('stock_purchase_returns')->insertGetId([
                    'stock_purchase_id' => $purchase,
                    'supplier_id' => $record->supplier_id,
                    'reference' => $returnReference,
                    'return_date' => $validated['return_date'],
                    'credit_amount' => $totalCredit,
                    'reason' => $validated['reason'],
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($returnRows as [$item, $quantity, $lineCredit]) {
                    $product = Product::query()->findOrFail($item->product_id);

                    $inventory->changeStock(
                        $product,
                        $receiving->variantFor($item, $product),
                        -$quantity,
                        'purchase_return',
                        $returnReference,
                        (float) $item->unit_cost,
                        $validated['reason'],
                        $request->user()?->id,
                        'stock_purchase_return',
                        $returnId,
                        'Return to supplier',
                    );

                    DB::table('stock_purchase_return_items')->insert([
                        'stock_purchase_return_id' => $returnId,
                        'stock_purchase_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $quantity,
                        'unit_cost' => $item->unit_cost,
                        'line_credit' => $lineCredit,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('stock_purchase_items')->where('id', $item->id)->update([
                        'quantity_returned' => (int) $item->quantity_returned + $quantity,
                        'updated_at' => now(),
                    ]);
                }

                $this->refreshBalance($purchase);
            });
        } catch (Throwable $exception) {
            return back()->withErrors(['return' => $exception->getMessage()]);
        }

        return back()->with('success', 'Supplier return posted and inventory reduced successfully.');
    }

    public function cancel(Request $request, int $purchase): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $purchase): void {
                $record = DB::table('stock_purchases')->where('id', $purchase)->lockForUpdate()->first();
                if (! $record) throw new RuntimeException('Purchase was not found.');
                if ($record->status === 'cancelled') throw new RuntimeException('Purchase is already cancelled.');

                $received = (int) DB::table('stock_purchase_items')->where('stock_purchase_id', $purchase)->sum('quantity_received');
                $payments = (float) DB::table('stock_purchase_payments')->where('stock_purchase_id', $purchase)->sum('amount');
                if ($received > 0) throw new RuntimeException('A purchase that has received stock cannot be cancelled. Return received stock instead.');
                if ($payments > 0) throw new RuntimeException('A purchase with recorded payments cannot be cancelled until those payments are resolved.');

                DB::table('stock_purchases')->where('id', $purchase)->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => $request->user()?->id,
                    'cancellation_reason' => $validated['reason'],
                    'balance_due' => 0,
                    'payment_status' => 'cancelled',
                    'updated_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            return back()->withErrors(['cancel' => $exception->getMessage()]);
        }

        return back()->with('success', 'Purchase cancelled successfully. No inventory was changed.');
    }

    private function refreshBalance(int $purchase): void
    {
        $record = DB::table('stock_purchases')->where('id', $purchase)->first();
        if (! $record || $record->status === 'cancelled') return;

        $paid = (float) DB::table('stock_purchase_payments')->where('stock_purchase_id', $purchase)->sum('amount');
        $credits = (float) DB::table('stock_purchase_returns')->where('stock_purchase_id', $purchase)->sum('credit_amount');
        $payable = max(0, (float) $record->total - $credits);
        $balance = max(0, $payable - $paid);
        $status = $balance <= 0.001 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid');

        DB::table('stock_purchases')->where('id', $purchase)->update([
            'amount_paid' => $paid,
            'balance_due' => $balance,
            'payment_status' => $status,
            'updated_at' => now(),
        ]);
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
    }
}
