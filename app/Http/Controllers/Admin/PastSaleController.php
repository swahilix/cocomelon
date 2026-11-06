<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditService;
use App\Services\InventoryService;
use App\Services\PostingGuard;
use App\Services\ProductUnitService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Sales that happened earlier and are entered now with their real date. They
 * reduce stock and appear in sales and profit reports for that date, but are
 * not added to any cash register session.
 */
class PastSaleController extends Controller
{
    public function create(): Response
    {
        $products = Product::query()
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->whereIn('status', ['draft', 'active'])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'stock_quantity', 'track_stock'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->current_price,
                'stock_quantity' => (int) $product->stock_quantity,
                'track_stock' => (bool) $product->track_stock,
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'name' => $variant->display_name,
                    'price' => $variant->price !== null ? (float) ($variant->sale_price ?? $variant->price) : (float) $product->current_price,
                    'stock_quantity' => (int) $variant->stock_quantity,
                ])->values(),
            ]);

        return Inertia::render('Admin/Sales/PastSale', [
            'products' => $products,
            'paymentMethods' => DB::table('payment_methods')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_cash']),
            'customers' => DB::table('pos_customers')->orderBy('name')->get(['id', 'name', 'phone']),
            'today' => now()->toDateString(),
            'recent' => DB::table('pos_sales')
                ->where('is_backdated', true)
                ->latest('id')
                ->limit(10)
                ->get(['id', 'reference', 'customer_name', 'total', 'created_at']),
        ]);
    }

    public function store(Request $request, InventoryService $inventory, ProductUnitService $units, PostingGuard $guard, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'sale_date' => ['required', 'date', 'before_or_equal:today'],
            'sale_time' => ['nullable', 'date_format:H:i'],
            'customer_id' => ['nullable', 'integer', 'exists:pos_customers,id'],
            'customer_name' => ['nullable', 'string', 'max:160'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'save_customer' => ['boolean'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ], [
            'sale_date.before_or_equal' => 'A past sale cannot be dated in the future.',
            'items.required' => 'Add at least one item.',
        ]);

        $saleAt = CarbonImmutable::parse($validated['sale_date'].' '.($validated['sale_time'] ?? '12:00'));
        if ($saleAt->isFuture()) {
            $saleAt = now()->toImmutable();
        }

        $lines = $this->lines($validated['items']);

        try {
            $saleId = DB::transaction(function () use ($validated, $request, $inventory, $units, $guard, $audit, $saleAt, $lines): int {
                $guard->claim($request, 'pos.past_sale', 'pos_sale');
                $paymentMethod = DB::table('payment_methods')->where('id', $validated['payment_method_id'])->first();

                $customerId = $validated['customer_id'] ?? null;
                $customerName = trim((string) ($validated['customer_name'] ?? ''));
                $customerPhone = trim((string) ($validated['customer_phone'] ?? ''));
                if ($customerId) {
                    $customer = DB::table('pos_customers')->where('id', $customerId)->first();
                    $customerName = $customerName ?: (string) $customer->name;
                    $customerPhone = $customerPhone ?: (string) $customer->phone;
                } elseif (($validated['save_customer'] ?? false) && $customerName !== '') {
                    $customerId = DB::table('pos_customers')->insertGetId([
                        'name' => $customerName,
                        'phone' => $customerPhone ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $subtotal = round((float) collect($lines)->sum(fn ($line) => $line['quantity'] * $line['unit_price']), 2);
                $discount = min(round((float) ($validated['discount'] ?? 0), 2), $subtotal);
                $total = max(0, $subtotal - $discount);
                $reference = 'POS-'.$saleAt->format('ymd-Hi').'-'.strtoupper(Str::random(4));

                $saleId = DB::table('pos_sales')->insertGetId([
                    'cash_register_session_id' => null,
                    'payment_method_id' => $paymentMethod->id,
                    'reference' => $reference,
                    'customer_id' => $customerId,
                    'customer_name' => $customerName ?: null,
                    'customer_phone' => $customerPhone ?: null,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'amount_paid' => $total,
                    'change_due' => 0,
                    'payment_method' => $paymentMethod->name,
                    'payment_reference' => $validated['payment_reference'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'is_backdated' => true,
                    'created_by' => $request->user()?->id,
                    'created_at' => $saleAt,
                    'updated_at' => now(),
                ]);

                foreach ($lines as $line) {
                    /** @var Product $product */
                    $product = Product::query()->findOrFail($line['product']->id);
                    $variant = $line['variant'];
                    $available = $variant ? (int) $variant->fresh()->stock_quantity : (int) $product->stock_quantity;
                    $label = $variant ? "{$product->name} — {$variant->display_name}" : $product->name;

                    if ($product->track_stock && $available < $line['quantity']) {
                        throw new RuntimeException("Not enough stock for {$label}: {$available} in stock. Enter its purchase (or opening stock) first, dated before this sale.");
                    }

                    $unitCost = (float) $product->average_cost;
                    $saleItemId = DB::table('pos_sale_items')->insertGetId([
                        'pos_sale_id' => $saleId,
                        'product_id' => $product->id,
                        'product_name' => $label,
                        'sku' => $variant?->sku ?: $product->sku,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'unit_cost' => $unitCost,
                        'line_total' => round($line['quantity'] * $line['unit_price'], 2),
                        'created_at' => $saleAt,
                        'updated_at' => now(),
                    ]);

                    $units->consumeForSale($product, $line['quantity'], [], $saleId, $saleItemId);

                    $inventory->changeStock(
                        $product,
                        $variant,
                        -$line['quantity'],
                        'pos_sale',
                        $reference,
                        $unitCost,
                        'Past sale on '.$saleAt->format('d M Y').($customerName ? ' · '.$customerName : ''),
                        $request->user()?->id,
                        'pos_sale',
                        $saleId,
                        'Past sale',
                    );
                }

                $audit->record($request, 'pos.past_sale_recorded', 'pos_sale', $saleId, $reference, null, DB::table('pos_sales')->where('id', $saleId)->first(), [
                    'sale_date' => $saleAt->toDateTimeString(),
                ]);

                return $saleId;
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['items' => $exception->getMessage()])->withInput();
        }

        $sale = DB::table('pos_sales')->where('id', $saleId)->first(['id', 'reference', 'total']);

        return redirect()
            ->route('admin.sales.past.create')
            ->with('success', "Past sale {$sale->reference} recorded for {$saleAt->format('d M Y')}. Stock was reduced; the cash register was not changed.");
    }

    /**
     * @return array<int, array{product: Product, variant: ?ProductVariant, quantity: int, unit_price: float}>
     */
    private function lines(array $items): array
    {
        $products = Product::query()
            ->with(['variants' => fn ($query) => $query->where('is_active', true)])
            ->whereIn('id', collect($items)->pluck('product_id')->unique()->all())
            ->get()
            ->keyBy('id');

        return collect($items)->values()->map(function (array $item, int $index) use ($products): array {
            $product = $products->get((int) $item['product_id']);
            $variantId = (int) ($item['product_variant_id'] ?? 0);
            $variant = null;

            if ($product->variants->isNotEmpty()) {
                $variant = $product->variants->firstWhere('id', $variantId);
                if (! $variant) {
                    throw ValidationException::withMessages(["items.$index.product_variant_id" => "Choose which variant of {$product->name} was sold."]);
                }
            } elseif ($variantId > 0) {
                throw ValidationException::withMessages(["items.$index.product_variant_id" => "{$product->name} has no variants."]);
            }

            return [
                'product' => $product,
                'variant' => $variant,
                'quantity' => (int) $item['quantity'],
                'unit_price' => round((float) $item['unit_price'], 2),
            ];
        })->all();
    }
}
