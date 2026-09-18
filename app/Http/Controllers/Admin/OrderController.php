<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ConversionTrackingService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Orders/Index', [
            'orders' => Order::query()
                ->when($request->filled('search'), fn ($query) => $query->where(fn ($search) => $search
                    ->where('order_number', 'like', '%'.$request->search.'%')
                    ->orWhere('customer_name', 'like', '%'.$request->search.'%')
                    ->orWhere('phone', 'like', '%'.$request->search.'%')))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function show(Order $order): Response
    {
        return Inertia::render('Admin/Orders/Show', [
            'order' => $order->load(['items', 'statusHistory' => fn ($query) => $query->oldest()]),
            'inventoryMovements' => DB::table('stock_movements')
                ->join('products', 'products.id', '=', 'stock_movements.product_id')
                ->where('stock_movements.source_type', 'online_order')
                ->where('stock_movements.source_id', $order->id)
                ->select('stock_movements.*', 'products.name as product_name', 'products.sku')
                ->orderBy('stock_movements.id')
                ->get(),
        ]);
    }

    public function update(Request $request, Order $order, InventoryService $inventory, ConversionTrackingService $conversions): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,processing,ready,shipped,completed,cancelled'],
            'payment_status' => ['required', 'in:pending,paid,failed,refunded'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($request, $order, $data, $inventory, $conversions): void {
                $lockedOrder = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);
                $oldStatus = $lockedOrder->status;
                $oldPaymentStatus = $lockedOrder->payment_status;

                if ($oldStatus === 'cancelled' && $data['status'] !== 'cancelled' && $lockedOrder->inventory_restored_at) {
                    throw new RuntimeException('A cancelled order whose stock was restored cannot be reopened. Create a new order instead.');
                }

                if ($data['status'] === 'cancelled' && $oldStatus !== 'cancelled') {
                    if (in_array($oldStatus, ['shipped', 'completed'], true)) {
                        throw new RuntimeException('Shipped or completed orders cannot use the pre-fulfillment cancellation flow.');
                    }

                    if ($lockedOrder->inventory_posted_at && ! $lockedOrder->inventory_restored_at) {
                        foreach ($lockedOrder->items as $item) {
                            if (! $item->product_id) {
                                continue;
                            }

                            $product = Product::query()->find($item->product_id);
                            if (! $product) {
                                continue;
                            }

                            $lastCost = (float) (DB::table('stock_movements')
                                ->where('source_type', 'online_order')
                                ->where('source_id', $lockedOrder->id)
                                ->where('product_id', $product->id)
                                ->where('quantity', '<', 0)
                                ->latest('id')
                                ->value('unit_cost') ?? 0);

                            if ($item->variant_id) {
                                $variant = ProductVariant::query()->lockForUpdate()->find($item->variant_id);
                                if ($variant && (int) $variant->product_id === (int) $product->id) {
                                    $variant->increment('stock_quantity', (int) $item->quantity);
                                }
                            }

                            $inventory->increase(
                                $product,
                                (int) $item->quantity,
                                'online_order_cancellation',
                                'CANCEL-'.$lockedOrder->order_number,
                                $lastCost,
                                'Stock restored after online order cancellation',
                                $request->user()?->id,
                                'online_order',
                                $lockedOrder->id,
                                'Online order cancelled',
                            );
                        }

                        $lockedOrder->inventory_restored_at = now();
                    }
                }

                $changed = $oldStatus !== $data['status'] || $oldPaymentStatus !== $data['payment_status'];
                $lockedOrder->status = $data['status'];
                $lockedOrder->payment_status = $data['payment_status'];
                $lockedOrder->save();

                if ($oldStatus !== 'completed' && $lockedOrder->status === 'completed') {
                    $conversions->recordCompletedOrder($lockedOrder);
                }

                if ($changed || filled($data['note'] ?? null)) {
                    $lockedOrder->statusHistory()->create([
                        'user_id' => $request->user()?->id,
                        'status' => $lockedOrder->status,
                        'payment_status' => $lockedOrder->payment_status,
                        'note' => $data['note'] ?? null,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return back()->with('success', 'Order updated.');
    }
}
