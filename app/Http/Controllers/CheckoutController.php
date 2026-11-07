<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\ConversionTrackingService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CheckoutController extends Controller
{
    public function create(Request $request, CartService $cart, ConversionTrackingService $conversions): Response
    {
        $summary = $cart->summary();
        abort_if($summary['items']->isEmpty(), 404);

        $conversions->record(
            $request,
            'checkout_start',
            null,
            ['item_count' => (int) $summary['count']],
            (float) $summary['total'],
            (int) $summary['count'],
        );

        return Inertia::render('Store/Checkout', ['cart' => $summary]);
    }

    public function store(Request $request, CartService $cart, InventoryService $inventory, ConversionTrackingService $conversions): RedirectResponse
    {
        $business = DB::table('business_settings')->where('id', 1)->first();
        $paymentSettingsReady = Schema::hasColumn('business_settings', 'payment_mobile_money_enabled');
        $enabledPaymentMethods = array_keys(array_filter([
            'mobile_money' => $paymentSettingsReady ? (bool) ($business?->payment_mobile_money_enabled ?? true) : true,
            'bank_transfer' => $paymentSettingsReady ? (bool) ($business?->payment_bank_transfer_enabled ?? true) : true,
            'cash_on_delivery' => $paymentSettingsReady ? (bool) ($business?->payment_cash_on_delivery_enabled ?? true) : true,
        ]));

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'fulfillment_method' => ['nullable', 'in:delivery,pickup'],
            'address_line_1' => ['nullable', 'required_if:fulfillment_method,delivery', 'string', 'max:190'],
            'address_line_2' => ['nullable', 'string', 'max:190'],
            'city' => ['nullable', 'required_if:fulfillment_method,delivery', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'string', 'max:40'],
        ]);

        if (! in_array($data['payment_method'], $enabledPaymentMethods, true)) {
            return back()->withErrors([
                'payment_method' => 'That payment method is not currently available. Choose one of the payment options shown at checkout.',
            ])->withInput();
        }

        $deliveryEnabled = (bool) ($business?->delivery_enabled ?? true);
        $pickupEnabled = (bool) ($business?->pickup_enabled ?? false);

        if (blank($data['fulfillment_method'] ?? null)) {
            $data['fulfillment_method'] = $deliveryEnabled ? 'delivery' : 'pickup';
        }

        if ($data['fulfillment_method'] === 'delivery' && (blank($data['address_line_1'] ?? null) || blank($data['city'] ?? null))) {
            return back()->withErrors([
                'address_line_1' => 'Enter a delivery address.',
                'city' => 'Enter the delivery area or district.',
            ])->withInput();
        }

        if ($data['fulfillment_method'] === 'delivery' && ! $deliveryEnabled) {
            return back()->withErrors(['fulfillment_method' => 'Delivery is currently unavailable.'])->withInput();
        }

        if ($data['fulfillment_method'] === 'pickup' && ! $pickupEnabled) {
            return back()->withErrors(['fulfillment_method' => 'Store pickup is currently unavailable.'])->withInput();
        }

        if ($data['fulfillment_method'] === 'pickup') {
            $data['address_line_1'] = $business?->pickup_location ?: $business?->address ?: 'Prinx Tech store pickup';
            $data['address_line_2'] = null;
            $data['city'] = $business?->city ?: $business?->region ?: 'Store pickup';
            $data['region'] = $business?->region;
        }

        $expectedCouponCode = $request->session()->get('cart_coupon_code');
        $summary = $cart->summary();
        abort_if($summary['items']->isEmpty(), 422, 'Your cart is empty.');
        $attribution = $conversions->attributionForOrder($request);

        if ($expectedCouponCode && ! $summary['coupon']) {
            return back()->withErrors([
                'checkout' => 'The coupon applied to this order is no longer valid. Please review your cart total before placing the order.',
            ])->withInput();
        }

        try {
            $shippingTotal = $data['fulfillment_method'] === 'pickup' ? 0 : (float) $summary['shipping'];
            $grandTotal = max(0, (float) $summary['subtotal'] + $shippingTotal - (float) $summary['discount']);

            $order = DB::transaction(function () use ($data, $summary, $shippingTotal, $grandTotal, $request, $inventory, $attribution): Order {
                $order = Order::create([
                    ...$data,
                    'order_number' => 'PX-'.now()->format('ymd').'-'.strtoupper(Str::random(8)),
                    'user_id' => $request->user()?->id,
                    ...$attribution,
                    'coupon_id' => $summary['coupon']['id'] ?? null,
                    'coupon_code' => $summary['coupon']['code'] ?? null,
                    'country' => config('store.country'),
                    'subtotal' => $summary['subtotal'],
                    'shipping_total' => $shippingTotal,
                    'discount_total' => $summary['discount'],
                    'grand_total' => $grandTotal,
                    'currency' => config('store.currency'),
                    // Keep the in-memory model aligned with the database defaults.
                    // The initial history entry below reads these values before any
                    // model refresh, so relying only on DB defaults leaves them null.
                    'payment_status' => 'pending',
                    'status' => 'pending',
                ]);

                foreach ($summary['items'] as $line) {
                    $product = Product::query()->lockForUpdate()->find($line['product']->id);
                    $variant = ! empty($line['variant']?->id)
                        ? ProductVariant::query()->lockForUpdate()->find($line['variant']->id)
                        : null;

                    if (! $product || ! $product->isLive()) {
                        abort(422, "{$line['product']->name} is no longer available.");
                    }

                    if ($variant) {
                        if ((int) $variant->product_id !== (int) $product->id || ! $variant->is_active || $variant->stock_quantity < (int) $line['quantity']) {
                            abort(422, "{$line['display_name']} no longer has enough stock.");
                        }
                    } elseif (! $product->in_stock) {
                        abort(422, "{$line['product']->name} is no longer available.");
                    }

                    $unitCost = (float) $product->average_cost;

                    $order->items()->create([
                        'product_id' => $product->id,
                        'variant_id' => $variant?->id,
                        'product_name' => $product->name,
                        'variant_name' => $variant?->display_name,
                        'sku' => $variant?->sku ?: $product->sku,
                        'variant_sku' => $variant?->sku,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'unit_cost' => $unitCost,
                        'line_total' => $line['line_total'],
                    ]);

                    if ($variant) {
                        $variant->decrement('stock_quantity', (int) $line['quantity']);
                    }

                    $inventory->decrease(
                        $product,
                        (int) $line['quantity'],
                        'online_order',
                        $order->order_number,
                        $unitCost,
                        $data['customer_name'],
                        $request->user()?->id,
                        'online_order',
                        $order->id,
                        'Online checkout',
                    );
                }

                if (! empty($summary['coupon']['id'])) {
                    Coupon::query()->whereKey($summary['coupon']['id'])->increment('usage_count');
                }

                $order->forceFill(['inventory_posted_at' => now()])->save();
                $order->statusHistory()->create([
                    'user_id' => $request->user()?->id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'note' => $data['fulfillment_method'] === 'pickup'
                        ? 'Store pickup order placed and inventory reserved at checkout.'
                        : 'Delivery order placed and inventory reserved at checkout.',
                ]);

                return $order;
            });
        } catch (Throwable $exception) {
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $exception;
            }

            return back()->withErrors(['checkout' => $exception->getMessage()])->withInput();
        }

        $cart->clear();
        $request->session()->put('last_order_number', $order->order_number);

        return redirect()->route('checkout.success', $order->order_number)->with('success', 'Your order has been received.');
    }

    public function success(Request $request, string $orderNumber): Response
    {
        $order = Order::where('order_number', $orderNumber)->with('items')->firstOrFail();
        $user = $request->user();
        $allowed = $request->session()->get('last_order_number') === $order->order_number
            || ($user && ($user->is_admin || $order->user_id === $user->id));

        abort_unless($allowed, 404);

        return Inertia::render('Store/OrderSuccess', ['order' => $order]);
    }
}