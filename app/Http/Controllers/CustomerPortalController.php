<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPortalController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $orders = $this->ordersQuery($request)->get();
        $payments = $this->paymentRecords($request);
        $invoiceCount = $orders->count() + $this->posInvoices($request)->count();

        return Inertia::render('Store/Account/Dashboard', [
            'summary' => [
                'orders' => $orders->count(),
                'active_orders' => $orders->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'payments' => $payments->count(),
                'paid_total' => (float) $payments->where('status', 'paid')->sum('amount'),
                'invoices' => $invoiceCount,
            ],
            'recentOrders' => $orders->take(4)->values(),
        ]);
    }

    public function orders(Request $request): Response
    {
        return Inertia::render('Store/Account/Orders', [
            'orders' => $this->ordersQuery($request)
                ->with(['items:id,order_id,product_name,quantity', 'statusHistory:id,order_id,status,payment_status,note,created_at'])
                ->get(),
        ]);
    }

    public function order(Request $request, string $orderNumber): Response
    {
        $order = $this->ordersQuery($request)
            ->with(['items', 'statusHistory' => fn ($query) => $query->latest('id')])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return Inertia::render('Store/Account/OrderShow', ['order' => $order]);
    }

    public function payments(Request $request): Response
    {
        return Inertia::render('Store/Account/Payments', [
            'payments' => $this->paymentRecords($request)->values(),
        ]);
    }

    public function invoices(Request $request): Response
    {
        $online = $this->ordersQuery($request)->get()->map(fn (Order $order): array => [
            'source' => 'order',
            'id' => $order->order_number,
            'reference' => $order->order_number,
            'date' => $order->created_at,
            'total' => (float) $order->grand_total,
            'status' => $order->payment_status,
            'url' => '/account/invoices/order/'.$order->order_number,
        ]);

        $pos = $this->posInvoices($request)->map(fn ($invoice): array => [
            'source' => 'pos',
            'id' => (string) $invoice->id,
            'reference' => $invoice->reference,
            'date' => $invoice->document_date ?: $invoice->created_at,
            'total' => (float) $invoice->total,
            'status' => $invoice->status,
            'url' => '/account/invoices/pos/'.$invoice->id,
        ]);

        return Inertia::render('Store/Account/Invoices', [
            'invoices' => $online->concat($pos)->sortByDesc('date')->values(),
        ]);
    }

    public function invoice(Request $request, string $source, string $reference): Response
    {
        abort_unless(in_array($source, ['order', 'pos'], true), 404);

        if ($source === 'order') {
            $order = $this->ordersQuery($request)->with('items')->where('order_number', $reference)->firstOrFail();

            return Inertia::render('Store/Account/InvoiceShow', [
                'invoice' => [
                    'source' => 'order',
                    'reference' => $order->order_number,
                    'date' => $order->created_at,
                    'due_date' => null,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->phone,
                    'customer_email' => $order->email,
                    'subtotal' => (float) $order->subtotal,
                    'discount' => (float) $order->discount_total,
                    'shipping' => (float) $order->shipping_total,
                    'total' => (float) $order->grand_total,
                    'status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'items' => $order->items->map(fn ($item): array => [
                        'name' => $item->product_name,
                        'sku' => $item->sku,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'line_total' => (float) $item->line_total,
                    ])->values(),
                ],
            ]);
        }

        abort_unless(Schema::hasTable('pos_documents'), 404);
        $id = filter_var($reference, FILTER_VALIDATE_INT);
        abort_unless($id, 404);

        $invoice = $this->posInvoices($request)->firstWhere('id', (int) $id);
        abort_unless($invoice, 404);

        return Inertia::render('Store/Account/InvoiceShow', [
            'invoice' => [
                'source' => 'pos',
                'reference' => $invoice->reference,
                'date' => $invoice->document_date ?: $invoice->created_at,
                'due_date' => $invoice->due_date,
                'customer_name' => $invoice->customer_name,
                'customer_phone' => $invoice->customer_phone,
                'customer_email' => $invoice->customer_email,
                'subtotal' => (float) $invoice->subtotal,
                'discount' => (float) $invoice->discount,
                'shipping' => 0,
                'total' => (float) $invoice->total,
                'status' => $invoice->status,
                'payment_method' => null,
                'items' => DB::table('pos_document_items')
                    ->where('pos_document_id', $invoice->id)
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($item): array => [
                        'name' => $item->product_name,
                        'sku' => $item->sku,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'line_total' => (float) $item->line_total,
                    ])->values(),
            ],
        ]);
    }

    private function ordersQuery(Request $request): Builder
    {
        $user = $request->user();
        abort_if(! $user || $user->is_admin, 403);

        return Order::query()->where('user_id', $user->id)->latest('id');
    }

    private function paymentRecords(Request $request): Collection
    {
        $online = $this->ordersQuery($request)->get()->map(fn (Order $order): array => [
            'source' => 'Online order',
            'reference' => $order->order_number,
            'amount' => (float) $order->grand_total,
            'method' => str_replace('_', ' ', $order->payment_method),
            'payment_reference' => null,
            'status' => $order->payment_status,
            'date' => $order->created_at,
        ]);

        if (! Schema::hasTable('pos_sales')) {
            return $online->sortByDesc('date')->values();
        }

        $variants = $this->phoneVariants((string) $request->user()->phone);
        if ($variants === []) {
            return $online->sortByDesc('date')->values();
        }

        $customerIds = $this->posCustomerIds($variants);
        $hasCustomerId = Schema::hasColumn('pos_sales', 'customer_id');
        $hasPaymentReference = Schema::hasColumn('pos_sales', 'payment_reference');

        $pos = DB::table('pos_sales')
            ->where(function ($query) use ($variants, $customerIds, $hasCustomerId): void {
                $query->whereIn('customer_phone', $variants);
                if ($hasCustomerId && $customerIds !== []) {
                    $query->orWhereIn('customer_id', $customerIds);
                }
            })
            ->latest('id')
            ->get()
            ->map(fn ($sale): array => [
                'source' => 'Store payment',
                'reference' => $sale->reference,
                'amount' => (float) $sale->amount_paid,
                'method' => str_replace('_', ' ', (string) $sale->payment_method),
                'payment_reference' => $hasPaymentReference ? ($sale->payment_reference ?? null) : null,
                'status' => (float) $sale->amount_paid >= (float) $sale->total ? 'paid' : 'pending',
                'date' => $sale->created_at,
            ]);

        return $online->concat($pos)->sortByDesc('date')->values();
    }

    private function posInvoices(Request $request): Collection
    {
        if (! Schema::hasTable('pos_documents')) {
            return collect();
        }

        $variants = $this->phoneVariants((string) $request->user()->phone);
        if ($variants === []) {
            return collect();
        }

        $customerIds = $this->posCustomerIds($variants);
        $hasCustomerId = Schema::hasColumn('pos_documents', 'customer_id');

        return DB::table('pos_documents')
            ->where('type', 'invoice')
            ->where(function ($query) use ($variants, $customerIds, $hasCustomerId): void {
                $query->whereIn('customer_phone', $variants);
                if ($hasCustomerId && $customerIds !== []) {
                    $query->orWhereIn('customer_id', $customerIds);
                }
            })
            ->latest('id')
            ->get();
    }

    private function posCustomerIds(array $variants): array
    {
        if ($variants === [] || ! Schema::hasTable('pos_customers')) {
            return [];
        }

        return DB::table('pos_customers')->whereIn('phone', $variants)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (! $digits) {
            return [];
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '255'.$digits;
        }

        if (! str_starts_with($digits, '255') || strlen($digits) !== 12) {
            return [];
        }

        $local = substr($digits, 3);
        return array_values(array_unique(['+'.$digits, $digits, '0'.$local, $local]));
    }
}
