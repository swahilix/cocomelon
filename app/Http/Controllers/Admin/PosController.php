<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

class PosController extends Controller
{
    public function index(Request $request, CashRegisterService $registers): Response
    {
        $products = Product::query()
            ->with(['category:id,name'])
            ->where('status', 'active')
            ->select(['id', 'category_id', 'name', 'sku', 'stock_quantity', 'track_stock', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'primary_image'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category?->name ?: 'Other',
                'stock_quantity' => $product->stock_quantity,
                'track_stock' => $product->track_stock,
                'price' => $product->current_price,
                'image_url' => $product->image_url,
                'available' => ! $product->track_stock || $product->stock_quantity > 0,
            ]);

        $held = null;
        if ($request->filled('hold')) {
            $held = DB::table('pos_held_sales')->where('id', $request->integer('hold'))->first();
            if ($held) {
                $held->cart = json_decode($held->cart, true) ?: [];
            }
        }

        return Inertia::render('Admin/Pos/Index', [
            'products' => $products,
            'categories' => $products->pluck('category')->unique()->sort()->values(),
            'customers' => DB::table('pos_customers')->orderBy('name')->limit(500)->get(['id', 'name', 'phone', 'email']),
            'heldSales' => DB::table('pos_held_sales')->latest('id')->limit(30)->get(['id', 'reference', 'customer_name', 'customer_phone', 'created_at']),
            'resumeHold' => $held,
            'currentRegister' => $registers->currentOpenSession($request->user()?->id),
        ]);
    }

    public function store(Request $request, InventoryService $inventory, CashRegisterService $registers): RedirectResponse
    {
        $validated = $this->validatedSale($request);

        try {
            $saleId = DB::transaction(function () use ($validated, $request, $inventory, $registers): int {
                $register = $registers->requireOpenSession($request->user()?->id);
                $customerId = $validated['customer_id'] ?? null;
                if (! $customerId && ! empty($validated['save_customer']) && ! empty($validated['customer_name'])) {
                    $customerId = DB::table('pos_customers')->insertGetId([
                        'name' => $validated['customer_name'],
                        'phone' => $validated['customer_phone'] ?? null,
                        'email' => $validated['customer_email'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $reference = $this->reference('POS');
                $subtotal = (float) collect($validated['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_price']);
                $discount = min((float) ($validated['discount'] ?? 0), $subtotal);
                $total = max(0, $subtotal - $discount);
                $paid = (float) $validated['amount_paid'];

                if ($paid < $total) {
                    throw new RuntimeException('Amount paid cannot be less than the sale total.');
                }

                $saleId = DB::table('pos_sales')->insertGetId([
                    'cash_register_session_id' => $register->id,
                    'reference' => $reference,
                    'customer_id' => $customerId,
                    'customer_name' => $validated['customer_name'] ?? null,
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'amount_paid' => $paid,
                    'change_due' => $paid - $total,
                    'payment_method' => $validated['payment_method'],
                    'payment_reference' => $validated['payment_reference'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($validated['items'] as $item) {
                    $product = Product::query()->findOrFail($item['product_id']);
                    $lineTotal = (float) $item['quantity'] * (float) $item['unit_price'];
                    $unitCost = (float) $product->average_cost;

                    DB::table('pos_sale_items')->insert([
                        'pos_sale_id' => $saleId,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'unit_cost' => $unitCost,
                        'line_total' => $lineTotal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $inventory->decrease(
                        $product,
                        $item['quantity'],
                        'pos_sale',
                        $reference,
                        $unitCost,
                        $validated['customer_name'] ?? 'Walk-in customer',
                        $request->user()?->id,
                        'pos_sale',
                        $saleId,
                        'POS sale',
                    );
                }

                if ($validated['payment_method'] === 'cash' && $total > 0) {
                    $registers->record(
                        $register->id,
                        'cash_sale',
                        'in',
                        $total,
                        'POS cash sale',
                        $reference,
                        $request->user()?->id,
                        'pos_sale',
                        $saleId,
                    );
                }

                if (! empty($validated['hold_id'])) {
                    DB::table('pos_held_sales')->where('id', $validated['hold_id'])->delete();
                }

                return $saleId;
            });
        } catch (Throwable $exception) {
            return back()->withErrors(['pos' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.pos.receipt', $saleId)->with('success', 'POS sale completed successfully.');
    }

    public function hold(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hold_id' => ['nullable', 'integer', 'exists:pos_held_sales,id'],
            'customer_id' => ['nullable', 'integer', 'exists:pos_customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:60'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:40'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $payload = [
            'customer_id' => $validated['customer_id'] ?? null,
            'customer_name' => $validated['customer_name'] ?? null,
            'customer_phone' => $validated['customer_phone'] ?? null,
            'cart' => json_encode($validated['items']),
            'discount' => $validated['discount'] ?? 0,
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_at' => now(),
        ];

        if (! empty($validated['hold_id'])) {
            DB::table('pos_held_sales')->where('id', $validated['hold_id'])->update($payload);
        } else {
            DB::table('pos_held_sales')->insert([
                ...$payload,
                'reference' => $this->reference('HLD'),
                'created_at' => now(),
            ]);
        }

        return redirect()->route('admin.pos.index')->with('success', 'Sale placed on hold.');
    }

    public function destroyHold(int $hold): RedirectResponse
    {
        DB::table('pos_held_sales')->where('id', $hold)->delete();
        return back()->with('success', 'Held sale removed.');
    }

    public function receipt(int $sale): Response
    {
        $record = DB::table('pos_sales')
            ->leftJoin('users', 'users.id', '=', 'pos_sales.created_by')
            ->where('pos_sales.id', $sale)
            ->select('pos_sales.*', 'users.name as cashier_name')
            ->first();
        abort_unless($record, 404);

        $items = DB::table('pos_sale_items')->where('pos_sale_id', $sale)->orderBy('id')->get();

        return Inertia::render('Admin/Pos/Receipt', [
            'sale' => $record,
            'items' => $items,
        ]);
    }

    private function validatedSale(Request $request): array
    {
        return $request->validate([
            'hold_id' => ['nullable', 'integer', 'exists:pos_held_sales,id'],
            'customer_id' => ['nullable', 'integer', 'exists:pos_customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:60'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'save_customer' => ['nullable', 'boolean'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank,card,other'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
    }
}
