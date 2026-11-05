<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Buyer',
            'email' => 'buyer@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);
        $this->category = Category::create(['name' => 'Accessories', 'type' => 'product', 'is_active' => true]);
    }

    public function test_purchase_order_is_created_with_items_and_estimated_cost(): void
    {
        $charger = $this->product('Fast Charger', ['last_purchase_cost' => 12000]);
        $supplier = Supplier::create(['name' => 'Kariakoo Wholesale', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get('/admin/purchase-orders/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PurchaseOrders/Form')
                ->where('products.0.estimated_cost', 12000));

        $this->actingAs($this->admin)
            ->post('/admin/purchase-orders', [
                'supplier_id' => $supplier->id,
                'order_date' => '2026-11-06',
                'expected_date' => '2026-11-10',
                'notes' => 'Deliver to Dodoma shop',
                'items' => [[
                    'product_id' => $charger->id,
                    'quantity' => 10,
                    'estimated_unit_cost' => 12500,
                    'notes' => 'Black only',
                ]],
            ])
            ->assertSessionHasNoErrors();

        $order = DB::table('purchase_orders')->first();
        $this->assertSame('draft', $order->status);
        $this->assertStringStartsWith('PO-', $order->reference);
        $this->assertSame('Kariakoo Wholesale', $order->supplier_name);

        $this->actingAs($this->admin)
            ->get('/admin/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PurchaseOrders/Show')
                ->where('estimate.total', 125000)
                ->where('estimate.quantity', 10)
                ->where('items.0.product_name', 'Fast Charger')
                ->where('items.0.notes', 'Black only'));

        // Creating a purchase order never changes stock.
        $this->assertSame(0, $charger->fresh()->stock_quantity);
    }

    public function test_export_lists_items_and_quantities_without_prices(): void
    {
        $charger = $this->product('Fast Charger');
        $order = $this->createOrder([['product_id' => $charger->id, 'quantity' => 7, 'estimated_unit_cost' => 98765]]);

        $response = $this->actingAs($this->admin)->get('/admin/purchase-orders/'.$order->id.'/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('PURCHASE ORDER', $csv);
        $this->assertStringContainsString($order->reference, $csv);
        $this->assertStringContainsString('Fast Charger', $csv);
        $this->assertStringContainsString(',7,', $csv);
        $this->assertStringNotContainsString('98765', $csv);
        $this->assertStringNotContainsStringIgnoringCase('cost', $csv);
        $this->assertStringNotContainsStringIgnoringCase('price', $csv);
    }

    public function test_variant_products_need_a_variant_and_items_cannot_repeat(): void
    {
        $phone = $this->product('Phone');
        ProductVariant::create(['product_id' => $phone->id, 'sku' => 'PH-128', 'label' => '128 GB', 'is_active' => true, 'stock_quantity' => 0]);

        $this->actingAs($this->admin)
            ->post('/admin/purchase-orders', ['order_date' => '2026-11-06', 'items' => [['product_id' => $phone->id, 'quantity' => 1]]])
            ->assertSessionHasErrors('items.0.product_variant_id');

        $charger = $this->product('Charger');
        $this->actingAs($this->admin)
            ->post('/admin/purchase-orders', ['order_date' => '2026-11-06', 'items' => [
                ['product_id' => $charger->id, 'quantity' => 1],
                ['product_id' => $charger->id, 'quantity' => 2],
            ]])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, DB::table('purchase_orders')->count());
    }

    public function test_order_can_be_edited_sent_and_cancelled(): void
    {
        $charger = $this->product('Charger');
        $cable = $this->product('USB Cable');
        $order = $this->createOrder([['product_id' => $charger->id, 'quantity' => 1]]);

        $this->actingAs($this->admin)
            ->put('/admin/purchase-orders/'.$order->id, [
                'order_date' => '2026-11-06',
                'items' => [['product_id' => $cable->id, 'quantity' => 20]],
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(['USB Cable'], DB::table('purchase_order_items')->where('purchase_order_id', $order->id)->pluck('product_name')->all());

        $this->actingAs($this->admin)->post('/admin/purchase-orders/'.$order->id.'/sent')->assertSessionHas('success');
        $this->assertSame('sent', DB::table('purchase_orders')->value('status'));

        $this->actingAs($this->admin)->post('/admin/purchase-orders/'.$order->id.'/cancel')->assertSessionHas('success');
        $this->assertSame('cancelled', DB::table('purchase_orders')->value('status'));

        $this->actingAs($this->admin)
            ->put('/admin/purchase-orders/'.$order->id, ['order_date' => '2026-11-06', 'items' => [['product_id' => $cable->id, 'quantity' => 5]]])
            ->assertSessionHasErrors('order');
    }

    public function test_order_becomes_a_purchase_when_goods_arrive(): void
    {
        $charger = $this->product('Charger', ['last_purchase_cost' => 9000]);
        $order = $this->createOrder([['product_id' => $charger->id, 'quantity' => 6, 'estimated_unit_cost' => 9000]], ['supplier_name' => 'Mlimani Traders']);

        $this->actingAs($this->admin)
            ->get('/admin/stock/entry?purchase_order='.$order->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Stock/Entry')
                ->where('initialMode', 'purchase')
                ->where('purchaseOrder.reference', $order->reference)
                ->where('purchaseOrder.lines.0.quantity', 6)
                ->where('purchaseOrder.lines.0.unit_cost', 9000));

        $payload = [
            'one_time_supplier_name' => 'Mlimani Traders',
            'purchase_date' => '2026-11-06',
            'status' => 'received',
            'payment_method' => 'cash',
            'purchase_order_id' => $order->id,
            'items' => [['product_id' => $charger->id, 'quantity' => 6, 'unit_cost' => 9500, 'selling_price' => 15000]],
        ];

        $this->actingAs($this->admin)->post('/admin/stock/purchases', $payload)->assertSessionHasNoErrors();

        $purchase = DB::table('stock_purchases')->first();
        $this->assertSame($order->id, (int) $purchase->purchase_order_id);
        $this->assertSame(6, $charger->fresh()->stock_quantity);

        $order = DB::table('purchase_orders')->find($order->id);
        $this->assertSame('converted', $order->status);
        $this->assertSame((int) $purchase->id, (int) $order->stock_purchase_id);

        // The same order cannot be purchased twice (a changed submission, so the
        // duplicate-submission guard does not stop it first).
        $payload['items'][0]['quantity'] = 5;
        $this->actingAs($this->admin)->post('/admin/stock/purchases', $payload)->assertSessionHasErrors('purchase_order_id');
        $this->assertSame(1, DB::table('stock_purchases')->count());
    }

    public function test_purchases_page_lists_and_filters_purchases(): void
    {
        $charger = $this->product('Charger');
        foreach (['Alpha Supplies' => 'received', 'Beta Traders' => 'ordered'] as $supplier => $status) {
            $this->actingAs($this->admin)->post('/admin/stock/purchases', [
                'one_time_supplier_name' => $supplier,
                'purchase_date' => '2026-11-06',
                'status' => $status,
                'payment_method' => 'cash',
                'items' => [['product_id' => $charger->id, 'quantity' => 2, 'unit_cost' => 1000, 'selling_price' => 2000]],
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($this->admin)
            ->get('/admin/purchases')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Purchases/Index')
                ->has('purchases.data', 2)
                ->where('totals.count', 2)
                ->where('totals.total', 4000));

        $this->actingAs($this->admin)
            ->get('/admin/purchases?status=ordered')
            ->assertInertia(fn (Assert $page) => $page
                ->has('purchases.data', 1)
                ->where('purchases.data.0.display_supplier_name', 'Beta Traders'));

        $this->actingAs($this->admin)
            ->get('/admin/purchases?search=Alpha')
            ->assertInertia(fn (Assert $page) => $page->has('purchases.data', 1));
    }

    private function product(string $name, array $attributes = []): Product
    {
        return Product::create([
            'category_id' => $this->category->id,
            'name' => $name,
            'sku' => strtoupper(str($name)->slug()->toString()),
            'description' => $name,
            'price' => 0,
            'condition' => 'new',
            'status' => 'draft',
            'track_stock' => true,
            'merchant_sync_enabled' => false,
            ...$attributes,
        ]);
    }

    private function createOrder(array $items, array $attributes = []): object
    {
        $this->actingAs($this->admin)
            ->post('/admin/purchase-orders', ['order_date' => '2026-11-06', 'items' => $items, ...$attributes])
            ->assertSessionHasNoErrors();

        return DB::table('purchase_orders')->latest('id')->first();
    }
}
