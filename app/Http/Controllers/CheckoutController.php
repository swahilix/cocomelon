<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CheckoutController extends Controller
{
    public function create(CartService $cart): Response
    {
        $summary = $cart->summary();
        abort_if($summary['items']->isEmpty(), 404);

        return Inertia::render('Store/Checkout', ['cart' => $summary]);
    }

    public function store(Request $request, CartService $cart, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'address_line_1' => ['required', 'string', 'max:190'],
            'address_line_2' => ['nullable', 'string', 'max:190'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:cash_on_delivery,mobile_money,bank_transfer'],
        ]);

        $summary = $cart->summary();
        abort_if($summary['items']->isEmpty(), 422, 'Your cart is empty.');

        try {
            $order = DB::transaction(function () use ($data, $summary, $request, $inventory): Order {
                $order = Order::create([
                    ...$data,
                    'order_number' => 'PX-'.now()->format('ymd').'-'.strtoupper(Str::random(8)),
                    'user_id' => $request->user()?->id,
                    'country' => config('store.country'),
                    'subtotal' => $summary['subtotal'],
                    'shipping_total' => $summary['shipping'],
                    'discount_total' => 0,
                    'grand_total' => $summary['total'],
                    'currency' => config('store.currency'),
                ]);

                foreach ($summary['items'] as $line) {
                    $product = Product::query()->find($line['product']->id);

                    if (! $product || $product->status !== 'active' || ! $product->in_stock) {
                        abort(422, "{$line['product']->name} is no longer available.");
                    }

                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                    ]);

                    $lastCost = (float) (DB::table('stock_purchase_items')
                        ->where('product_id', $product->id)
                        ->latest('id')
                        ->value('unit_cost') ?? 0);

                    $inventory->decrease(
                        $product,
                        (int) $line['quantity'],
                        'online_order',
                        $order->order_number,
                        $lastCost,
                        $data['customer_name'],
                        $request->user()?->id,
                        'online_order',
                        $order->id,
                        'Online checkout',
                    );
                }

                $order->forceFill(['inventory_posted_at' => now()])->save();
                $order->statusHistory()->create([
                    'user_id' => $request->user()?->id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'note' => 'Order placed and inventory reserved at checkout.',
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
