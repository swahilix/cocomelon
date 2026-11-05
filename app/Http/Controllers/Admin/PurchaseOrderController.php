<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Purchase orders: what to buy and how many. Printed and exported without
 * prices; the estimated cost is only shown inside the system.
 */
class PurchaseOrderController extends Controller
{
    public const OPEN_STATUSES = ['draft', 'sent'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $orders = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->leftJoin('stock_purchases', 'stock_purchases.id', '=', 'purchase_orders.stock_purchase_id')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                $inner->where('purchase_orders.reference', 'like', "%{$search}%")
                    ->orWhere('purchase_orders.supplier_name', 'like', "%{$search}%")
                    ->orWhere('suppliers.name', 'like', "%{$search}%")
                    ->orWhereExists(fn ($items) => $items->select(DB::raw(1))
                        ->from('purchase_order_items')
                        ->whereColumn('purchase_order_items.purchase_order_id', 'purchase_orders.id')
                        ->where(fn ($match) => $match->where('purchase_order_items.product_name', 'like', "%{$search}%")->orWhere('purchase_order_items.sku', 'like', "%{$search}%")));
            }))
            ->when(in_array($status, ['draft', 'sent', 'converted', 'cancelled'], true), fn ($query) => $query->where('purchase_orders.status', $status))
            ->select(
                'purchase_orders.*',
                DB::raw('COALESCE(suppliers.name, purchase_orders.supplier_name) as display_supplier_name'),
                'stock_purchases.reference as purchase_reference',
                DB::raw('(SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_items.purchase_order_id = purchase_orders.id) as line_count'),
                DB::raw('(SELECT COALESCE(SUM(quantity), 0) FROM purchase_order_items WHERE purchase_order_items.purchase_order_id = purchase_orders.id) as total_quantity'),
                DB::raw('(SELECT COALESCE(SUM(quantity * estimated_unit_cost), 0) FROM purchase_order_items WHERE purchase_order_items.purchase_order_id = purchase_orders.id) as estimated_total'),
            )
            ->latest('purchase_orders.id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/PurchaseOrders/Index', [
            'orders' => $orders,
            'filters' => ['search' => $search, 'status' => $status],
            'counts' => DB::table('purchase_orders')->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PurchaseOrders/Form', [
            'order' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $orderId = DB::transaction(function () use ($data, $request): int {
            $orderId = DB::table('purchase_orders')->insertGetId([
                ...$data['order'],
                'reference' => $this->reference(),
                'status' => 'draft',
                'created_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->saveItems($orderId, $data['items']);

            return $orderId;
        });

        return redirect()->route('admin.purchase-orders.show', $orderId)->with('success', 'Purchase order created. Print it or download it to send to your supplier.');
    }

    public function show(int $purchaseOrder): Response
    {
        $order = $this->findOrder($purchaseOrder);
        $items = $this->items($purchaseOrder);

        return Inertia::render('Admin/PurchaseOrders/Show', [
            'order' => $order,
            'items' => $items,
            'estimate' => [
                'total' => (float) $items->sum('estimated_total'),
                'missing' => $items->whereNull('estimated_unit_cost')->count(),
                'quantity' => (int) $items->sum('quantity'),
            ],
        ]);
    }

    public function edit(int $purchaseOrder): Response|RedirectResponse
    {
        $order = $this->findOrder($purchaseOrder);
        if (! in_array($order->status, self::OPEN_STATUSES, true)) {
            return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('error', 'Only draft or sent purchase orders can be edited.');
        }

        return Inertia::render('Admin/PurchaseOrders/Form', [
            'order' => [
                ...(array) $order,
                'items' => $this->items($purchaseOrder)->map(fn ($item) => [
                    'product_id' => (int) $item->product_id,
                    'product_variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : '',
                    'quantity' => (int) $item->quantity,
                    'estimated_unit_cost' => $item->estimated_unit_cost !== null ? (float) $item->estimated_unit_cost : '',
                    'notes' => $item->notes ?? '',
                ])->values(),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, int $purchaseOrder): RedirectResponse
    {
        $order = $this->findOrder($purchaseOrder);
        if (! in_array($order->status, self::OPEN_STATUSES, true)) {
            throw ValidationException::withMessages(['order' => 'Only draft or sent purchase orders can be edited.']);
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $purchaseOrder): void {
            DB::table('purchase_orders')->where('id', $purchaseOrder)->update([...$data['order'], 'updated_at' => now()]);
            DB::table('purchase_order_items')->where('purchase_order_id', $purchaseOrder)->delete();
            $this->saveItems($purchaseOrder, $data['items']);
        });

        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order updated.');
    }

    public function markSent(int $purchaseOrder): RedirectResponse
    {
        $order = $this->findOrder($purchaseOrder);
        if ($order->status !== 'draft') {
            return back()->with('error', 'Only a draft purchase order can be marked as sent.');
        }

        DB::table('purchase_orders')->where('id', $purchaseOrder)->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Purchase order marked as sent to the supplier.');
    }

    public function cancel(int $purchaseOrder): RedirectResponse
    {
        $order = $this->findOrder($purchaseOrder);
        if (! in_array($order->status, self::OPEN_STATUSES, true)) {
            return back()->with('error', 'This purchase order can no longer be cancelled.');
        }

        DB::table('purchase_orders')->where('id', $purchaseOrder)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Purchase order cancelled.');
    }

    /**
     * Excel-compatible CSV with items and quantities only (no prices).
     */
    public function export(int $purchaseOrder): StreamedResponse
    {
        $order = $this->findOrder($purchaseOrder);
        $items = $this->items($purchaseOrder);
        $business = DB::table('business_settings')->where('id', 1)->first();
        $businessName = $business->business_name ?? config('app.name');

        return response()->streamDownload(function () use ($order, $items, $business, $businessName): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel shows Swahili and special characters correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [$businessName]);
            $contact = collect([$business->phone ?? null, $business->email ?? null, $business->address ?? null])->filter()->implode(' · ');
            if ($contact !== '') {
                fputcsv($out, [$contact]);
            }
            fputcsv($out, []);
            fputcsv($out, ['PURCHASE ORDER', $order->reference]);
            fputcsv($out, ['Date', $order->order_date]);
            if ($order->expected_date) {
                fputcsv($out, ['Needed by', $order->expected_date]);
            }
            fputcsv($out, ['Supplier', $order->display_supplier_name ?: '']);
            if ($order->notes) {
                fputcsv($out, ['Notes', $order->notes]);
            }
            fputcsv($out, []);
            fputcsv($out, ['No.', 'Item', 'Variant', 'SKU', 'Quantity', 'Notes']);

            foreach ($items->values() as $index => $item) {
                fputcsv($out, [$index + 1, $item->product_name, $item->variant_name ?? '', $item->sku ?? '', (int) $item->quantity, $item->notes ?? '']);
            }

            fputcsv($out, []);
            fputcsv($out, ['', 'Total quantity', '', '', (int) $items->sum('quantity')]);
            fclose($out);
        }, Str::slug($order->reference).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function findOrder(int $id): object
    {
        $order = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->leftJoin('stock_purchases', 'stock_purchases.id', '=', 'purchase_orders.stock_purchase_id')
            ->leftJoin('users', 'users.id', '=', 'purchase_orders.created_by')
            ->where('purchase_orders.id', $id)
            ->select(
                'purchase_orders.*',
                DB::raw('COALESCE(suppliers.name, purchase_orders.supplier_name) as display_supplier_name'),
                'suppliers.phone as supplier_phone',
                'suppliers.email as supplier_email',
                'stock_purchases.reference as purchase_reference',
                'users.name as created_by_name',
            )
            ->first();

        abort_unless($order, 404);

        return $order;
    }

    private function items(int $orderId): Collection
    {
        return DB::table('purchase_order_items')
            ->leftJoin('products', 'products.id', '=', 'purchase_order_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'purchase_order_items.product_variant_id')
            ->where('purchase_order_items.purchase_order_id', $orderId)
            ->orderBy('purchase_order_items.sort_order')
            ->orderBy('purchase_order_items.id')
            ->select(
                'purchase_order_items.*',
                'products.slug as product_slug',
                DB::raw('CASE WHEN purchase_order_items.product_variant_id IS NULL THEN products.stock_quantity ELSE product_variants.stock_quantity END as current_stock'),
            )
            ->get()
            ->map(function ($item) {
                $item->estimated_total = $item->estimated_unit_cost !== null ? round((float) $item->estimated_unit_cost * (int) $item->quantity, 2) : null;

                return $item;
            });
    }

    /**
     * @return array{order: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Add at least one item to order.',
            'items.min' => 'Add at least one item to order.',
        ]);

        $products = Product::query()
            ->with(['variants' => fn ($query) => $query->where('is_active', true)])
            ->whereIn('id', collect($validated['items'])->pluck('product_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $items = collect($validated['items'])->values()->map(function (array $item, int $index) use ($products): array {
            $product = $products->get((int) $item['product_id']);
            $variantId = (int) ($item['product_variant_id'] ?? 0);
            $variant = null;

            if ($product->variants->isNotEmpty()) {
                $variant = $product->variants->firstWhere('id', $variantId);
                if (! $variant) {
                    throw ValidationException::withMessages(["items.$index.product_variant_id" => "Choose which variant of {$product->name}."]);
                }
            } elseif ($variantId > 0) {
                throw ValidationException::withMessages(["items.$index.product_variant_id" => "{$product->name} has no variants."]);
            }

            return [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'variant_name' => $variant?->display_name,
                'sku' => $variant?->sku ?: $product->sku,
                'quantity' => (int) $item['quantity'],
                'estimated_unit_cost' => filled($item['estimated_unit_cost'] ?? null) ? round((float) $item['estimated_unit_cost'], 2) : null,
                'notes' => filled($item['notes'] ?? null) ? trim($item['notes']) : null,
            ];
        });

        $duplicate = $items->countBy(fn ($item) => $item['product_id'].'-'.($item['product_variant_id'] ?? 0))->filter(fn ($count) => $count > 1);
        if ($duplicate->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Each item can appear only once. Increase its quantity instead.']);
        }

        $supplier = filled($validated['supplier_id'] ?? null) ? Supplier::query()->find($validated['supplier_id']) : null;

        return [
            'order' => [
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplier ? $supplier->name : (filled($validated['supplier_name'] ?? null) ? trim($validated['supplier_name']) : null),
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            ],
            'items' => $items->all(),
        ];
    }

    private function saveItems(int $orderId, array $items): void
    {
        $now = now();

        DB::table('purchase_order_items')->insert(collect($items)->values()->map(fn (array $item, int $index) => [
            ...$item,
            'purchase_order_id' => $orderId,
            'sort_order' => $index,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    /**
     * Products, variants and suppliers for the form, with an estimated unit
     * cost from the last purchase (or average cost).
     */
    private function formOptions(): array
    {
        $variantLastCost = DB::table('stock_purchase_items')
            ->whereNotNull('product_variant_id')
            ->whereIn('id', DB::table('stock_purchase_items')->whereNotNull('product_variant_id')->selectRaw('MAX(id)')->groupBy('product_variant_id'))
            ->pluck('unit_cost', 'product_variant_id');

        $products = Product::query()
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->whereIn('status', ['draft', 'active'])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'status', 'stock_quantity', 'low_stock_threshold', 'track_stock', 'last_purchase_cost', 'average_cost'])
            ->map(function (Product $product) use ($variantLastCost): array {
                $productCost = (float) $product->last_purchase_cost ?: ((float) $product->average_cost ?: null);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'stock_quantity' => (int) $product->stock_quantity,
                    'low_stock_threshold' => (int) $product->low_stock_threshold,
                    'track_stock' => (bool) $product->track_stock,
                    'estimated_cost' => $productCost,
                    'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                        'id' => $variant->id,
                        'name' => $variant->display_name,
                        'sku' => $variant->sku,
                        'stock_quantity' => (int) $variant->stock_quantity,
                        'estimated_cost' => isset($variantLastCost[$variant->id]) ? (float) $variantLastCost[$variant->id] : $productCost,
                    ])->values(),
                ];
            });

        return [
            'products' => $products,
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'today' => now()->toDateString(),
        ];
    }

    private function reference(): string
    {
        do {
            $reference = 'PO-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (DB::table('purchase_orders')->where('reference', $reference)->exists());

        return $reference;
    }
}
