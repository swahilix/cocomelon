<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\CashRegisterService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $source = $request->string('source')->toString();

        $pos = collect();
        if ($source !== 'online') {
            $pos = DB::table('pos_sales')
                ->leftJoin('users', 'users.id', '=', 'pos_sales.created_by')
                ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s->where('pos_sales.reference', 'like', "%{$search}%")
                    ->orWhere('pos_sales.customer_name', 'like', "%{$search}%")
                    ->orWhere('pos_sales.customer_phone', 'like', "%{$search}%")))
                ->select('pos_sales.id', 'pos_sales.reference', 'pos_sales.customer_name', 'pos_sales.customer_phone', 'pos_sales.total', 'pos_sales.payment_method', 'pos_sales.status', 'pos_sales.refunded_amount', 'pos_sales.created_at', 'users.name as cashier_name')
                ->latest('pos_sales.id')->limit(100)->get()
                ->map(fn ($sale) => [
                    'id' => $sale->id,
                    'reference' => $sale->reference,
                    'source' => 'pos',
                    'customer' => $sale->customer_name ?: 'Walk-in customer',
                    'phone' => $sale->customer_phone,
                    'amount' => (float) $sale->total,
                    'payment_status' => $sale->status === 'voided' ? 'voided' : ((float) $sale->refunded_amount > 0 ? 'refunded' : 'paid'),
                    'status' => $sale->status,
                    'staff' => $sale->cashier_name,
                    'created_at' => $sale->created_at,
                    'url' => "/admin/sales/pos/{$sale->id}",
                ]);
        }

        $online = collect();
        if ($source !== 'pos') {
            $online = Order::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")))
                ->latest()->limit(100)->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'reference' => $order->order_number,
                    'source' => 'online',
                    'customer' => $order->customer_name,
                    'phone' => $order->phone,
                    'amount' => (float) $order->total,
                    'payment_status' => $order->payment_status,
                    'status' => $order->status,
                    'staff' => null,
                    'created_at' => $order->created_at,
                    'url' => "/admin/orders/{$order->id}",
                ]);
        }

        return Inertia::render('Admin/Sales/Index', [
            'sales' => $pos->concat($online)->sortByDesc('created_at')->values(),
            'filters' => ['search' => $search, 'source' => $source],
            'summary' => [
                'pos_sales' => DB::table('pos_sales')->where('status', '!=', 'voided')->count(),
                'pos_revenue' => (float) DB::table('pos_sales')->where('status', '!=', 'voided')->sum('total'),
                'online_sales' => Order::count(),
                'online_revenue' => (float) Order::sum('total'),
                'refunds' => (float) DB::table('pos_sale_returns')->sum('refund_amount'),
            ],
        ]);
    }

    public function showPos(int $sale): Response
    {
        $record = DB::table('pos_sales')
            ->leftJoin('users', 'users.id', '=', 'pos_sales.created_by')
            ->leftJoin('users as void_user', 'void_user.id', '=', 'pos_sales.voided_by')
            ->where('pos_sales.id', $sale)
            ->select('pos_sales.*', 'users.name as cashier_name', 'void_user.name as voided_by_name')
            ->first();
        abort_unless($record, 404);

        $items = DB::table('pos_sale_items')->where('pos_sale_id', $sale)->orderBy('id')->get();
        $returns = DB::table('pos_sale_returns')
            ->leftJoin('users', 'users.id', '=', 'pos_sale_returns.created_by')
            ->where('pos_sale_returns.pos_sale_id', $sale)
            ->select('pos_sale_returns.*', 'users.name as processed_by')
            ->latest('pos_sale_returns.id')->get();

        $returnItems = DB::table('pos_sale_return_items')
            ->join('pos_sale_returns', 'pos_sale_returns.id', '=', 'pos_sale_return_items.pos_sale_return_id')
            ->where('pos_sale_returns.pos_sale_id', $sale)
            ->select('pos_sale_return_items.*', 'pos_sale_returns.reference as return_reference')
            ->get();

        return Inertia::render('Admin/Sales/ShowPos', [
            'sale' => $record,
            'items' => $items,
            'returns' => $returns,
            'returnItems' => $returnItems,
            'movements' => DB::table('stock_movements')
                ->join('products', 'products.id', '=', 'stock_movements.product_id')
                ->where('source_type', 'pos_sale')->where('source_id', $sale)
                ->orWhere(function ($q) use ($sale): void {
                    $q->where('source_type', 'pos_return')->whereIn('source_id', DB::table('pos_sale_returns')->where('pos_sale_id', $sale)->pluck('id'));
                })
                ->select('stock_movements.*', 'products.name as product_name')
                ->latest('stock_movements.id')->get(),
        ]);
    }

    public function returnPos(Request $request, int $sale, InventoryService $inventory, CashRegisterService $registers): RedirectResponse
    {
        $validated = $request->validate([
            'return_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'refund_method' => ['required', 'in:cash,mobile_money,bank,card,other'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'exists:pos_sale_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'items.*.restock' => ['required', 'boolean'],
        ]);

        try {
            DB::transaction(function () use ($validated, $sale, $request, $inventory, $registers): void {
                $register = $registers->requireOpenSession($request->user()?->id);
                $saleRow = DB::table('pos_sales')->where('id', $sale)->lockForUpdate()->first();
                if (! $saleRow || $saleRow->status === 'voided') {
                    throw new RuntimeException('This POS sale cannot be returned.');
                }

                $selected = collect($validated['items'])->filter(fn ($i) => (int) $i['quantity'] > 0);
                if ($selected->isEmpty()) {
                    throw new RuntimeException('Select at least one quantity to return.');
                }

                $returnReference = $this->reference('RET');
                $returnId = DB::table('pos_sale_returns')->insertGetId([
                    'cash_register_session_id' => $register->id,
                    'reference' => $returnReference,
                    'pos_sale_id' => $sale,
                    'return_date' => $validated['return_date'],
                    'refund_amount' => 0,
                    'refund_method' => $validated['refund_method'],
                    'reason' => $validated['reason'],
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $refund = 0.0;
                foreach ($selected as $input) {
                    $item = DB::table('pos_sale_items')->where('id', $input['sale_item_id'])->where('pos_sale_id', $sale)->lockForUpdate()->first();
                    if (! $item) throw new RuntimeException('A selected sale item is invalid.');

                    $remaining = (int) $item->quantity - (int) $item->quantity_returned;
                    $qty = (int) $input['quantity'];
                    if ($qty > $remaining) throw new RuntimeException("Return quantity exceeds remaining quantity for {$item->product_name}.");

                    $lineRefund = $qty * (float) $item->unit_price;
                    $refund += $lineRefund;

                    DB::table('pos_sale_return_items')->insert([
                        'pos_sale_return_id' => $returnId,
                        'pos_sale_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $qty,
                        'unit_price' => $item->unit_price,
                        'refund_amount' => $lineRefund,
                        'restock' => (bool) $input['restock'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('pos_sale_items')->where('id', $item->id)->increment('quantity_returned', $qty);

                    if ((bool) $input['restock']) {
                        $inventory->increase(
                            Product::query()->findOrFail($item->product_id),
                            $qty,
                            'pos_return',
                            $returnReference,
                            (float) $item->unit_cost,
                            $validated['reason'],
                            $request->user()?->id,
                            'pos_return',
                            $returnId,
                            'POS sale return',
                        );
                    }
                }

                DB::table('pos_sale_returns')->where('id', $returnId)->update(['refund_amount' => $refund, 'updated_at' => now()]);
                DB::table('pos_sales')->where('id', $sale)->update([
                    'refunded_amount' => DB::raw('refunded_amount + '.(float) $refund),
                    'status' => ((float) $saleRow->refunded_amount + $refund >= (float) $saleRow->total) ? 'returned' : 'partially_returned',
                    'updated_at' => now(),
                ]);

                if ($validated['refund_method'] === 'cash' && $refund > 0) {
                    $registers->record(
                        $register->id,
                        'cash_refund',
                        'out',
                        $refund,
                        'POS cash refund',
                        $returnReference,
                        $request->user()?->id,
                        'pos_return',
                        $returnId,
                    );
                }
            });
        } catch (Throwable $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('success', 'POS return recorded successfully.');
    }

    public function voidPos(Request $request, int $sale, InventoryService $inventory, CashRegisterService $registers): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        try {
            DB::transaction(function () use ($sale, $validated, $request, $inventory, $registers): void {
                $register = $registers->requireOpenSession($request->user()?->id);
                $saleRow = DB::table('pos_sales')->where('id', $sale)->lockForUpdate()->first();
                if (! $saleRow || $saleRow->status === 'voided') throw new RuntimeException('Sale is already voided or unavailable.');
                if ((float) $saleRow->refunded_amount > 0) throw new RuntimeException('A sale with posted returns cannot be voided.');

                $items = DB::table('pos_sale_items')->where('pos_sale_id', $sale)->get();
                foreach ($items as $item) {
                    $inventory->increase(
                        Product::query()->findOrFail($item->product_id),
                        (int) $item->quantity,
                        'pos_void',
                        $saleRow->reference,
                        (float) $item->unit_cost,
                        $validated['reason'],
                        $request->user()?->id,
                        'pos_sale',
                        $sale,
                        'POS sale void reversal',
                    );
                }

                DB::table('pos_sales')->where('id', $sale)->update([
                    'status' => 'voided',
                    'voided_at' => now(),
                    'voided_by' => $request->user()?->id,
                    'void_reason' => $validated['reason'],
                    'updated_at' => now(),
                ]);

                if ($saleRow->payment_method === 'cash' && (float) $saleRow->total > 0) {
                    $registers->record(
                        $register->id,
                        'cash_void_refund',
                        'out',
                        (float) $saleRow->total,
                        'Cash returned after POS void',
                        $saleRow->reference,
                        $request->user()?->id,
                        'pos_void',
                        $sale,
                    );
                }
            });
        } catch (Throwable $e) {
            return back()->withErrors(['void' => $e->getMessage()]);
        }

        return back()->with('success', 'POS sale voided and inventory reversed.');
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
    }
}
