<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductEntryFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Stock Admin',
            'email' => 'stock-admin@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);
        $this->category = Category::create(['name' => 'Phones', 'type' => 'product', 'is_active' => true]);
    }

    public function test_step_one_saves_details_only_and_opens_stock_page(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/products', $this->details([
            'name' => 'Tecno Spark 20',
            'low_stock_threshold' => 4,
            'warranty_duration_value' => 1,
            'warranty_duration_unit' => 'years',
            // Old price/stock fields are ignored by the new flow.
            'price' => 999999,
            'stock_quantity' => 50,
        ]));

        $product = Product::query()->where('name', 'Tecno Spark 20')->firstOrFail();
        $response->assertSessionHasNoErrors()->assertRedirect('/admin/products/'.$product->slug.'/stock');

        $this->assertSame(0.0, (float) $product->price);
        $this->assertSame(0, $product->stock_quantity);
        $this->assertSame('draft', $product->status);
        $this->assertSame(4, $product->low_stock_threshold);
        $this->assertSame('1 year warranty', $product->warranty);
        $this->assertSame(0, DB::table('stock_movements')->count());

        $this->actingAs($this->admin)
            ->get('/admin/products/'.$product->slug.'/stock')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Products/Stock')->where('product.opening_allowed', true));
    }

    public function test_product_cannot_be_published_before_it_has_a_price(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/products', $this->details(['name' => 'Unpriced', 'status' => 'active']))
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_opening_stock_adds_stock_cost_price_and_discount_immediately(): void
    {
        $product = $this->createProduct('Samsung A15');

        $this->actingAs($this->admin)
            ->post('/admin/products/'.$product->slug.'/opening-stock', [
                'lines' => [[
                    'quantity' => 10,
                    'unit_cost' => 300000,
                    'selling_price' => 400000,
                    'discount_type' => 'amount',
                    'discount_value' => 25000,
                ]],
                'publish' => true,
            ])
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(10, $product->stock_quantity);
        $this->assertSame(300000.0, (float) $product->average_cost);
        $this->assertSame(400000.0, (float) $product->price);
        $this->assertSame(375000.0, (float) $product->sale_price);
        $this->assertSame(375000.0, $product->current_price);
        $this->assertSame('active', $product->status);

        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'opening_stock', 'quantity' => 10]);
        $this->assertDatabaseHas('product_price_changes', ['product_id' => $product->id, 'source' => 'opening_stock', 'new_price' => 400000, 'new_sale_price' => 375000]);

        // Opening stock can only be entered once; further stock comes from purchases.
        $this->actingAs($this->admin)
            ->post('/admin/products/'.$product->slug.'/opening-stock', [
                'lines' => [['quantity' => 2, 'unit_cost' => 1, 'selling_price' => 2]],
            ])
            ->assertSessionHasErrors('lines');
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_variant_opening_stock_is_tracked_per_variant(): void
    {
        $this->actingAs($this->admin)->post('/admin/products', $this->details([
            'name' => 'iPhone 15',
            'variants' => [
                ['sku' => 'IP15-128', 'label' => '128 GB', 'is_active' => true],
                ['sku' => 'IP15-256', 'label' => '256 GB', 'is_active' => true],
            ],
        ]))->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'iPhone 15')->firstOrFail();
        $small = ProductVariant::query()->where('sku', 'IP15-128')->firstOrFail();
        $large = ProductVariant::query()->where('sku', 'IP15-256')->firstOrFail();
        $this->assertNull($small->price);
        $this->assertSame(0, $small->stock_quantity);

        $this->actingAs($this->admin)
            ->post('/admin/products/'.$product->slug.'/opening-stock', [
                'lines' => [
                    ['product_variant_id' => $small->id, 'quantity' => 3, 'unit_cost' => 2000000, 'selling_price' => 2500000, 'discount_type' => 'percent', 'discount_value' => 10],
                    ['product_variant_id' => $large->id, 'quantity' => 2, 'unit_cost' => 2600000, 'selling_price' => 3200000],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $small->fresh()->stock_quantity);
        $this->assertSame(2250000.0, (float) $small->fresh()->sale_price);
        $this->assertSame(2, $large->fresh()->stock_quantity);
        $this->assertSame(3200000.0, (float) $large->fresh()->price);

        $product->refresh();
        $this->assertSame(5, $product->stock_quantity);
        // The parent shows the cheapest variant price.
        $this->assertSame(2500000.0, (float) $product->price);
        $this->assertSame(2250000.0, (float) $product->sale_price);
        $this->assertDatabaseHas('stock_movements', ['product_variant_id' => $small->id, 'type' => 'opening_stock', 'quantity' => 3]);

        // A variant line is required for products with variants.
        $this->actingAs($this->admin)
            ->post('/admin/products/'.$product->slug.'/price', ['selling_price' => 1, 'discount_type' => 'none'])
            ->assertSessionHasErrors('product_variant_id');
    }

    public function test_received_purchase_updates_stock_and_prices_immediately(): void
    {
        $product = $this->createProduct('HP Laptop');

        $this->actingAs($this->admin)
            ->post('/admin/stock/purchases', $this->purchase($product, 'received', [
                'quantity' => 4,
                'unit_cost' => 1000000,
                'selling_price' => 1300000,
                'discount_type' => 'percent',
                'discount_value' => 5,
            ]))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(4, $product->stock_quantity);
        $this->assertSame(1000000.0, (float) $product->average_cost);
        $this->assertSame(1300000.0, (float) $product->price);
        $this->assertSame(1235000.0, (float) $product->sale_price);

        $purchase = DB::table('stock_purchases')->first();
        $this->assertSame('received', $purchase->status);
        $this->assertNotNull(DB::table('stock_purchase_items')->value('prices_applied_at'));
        $this->assertDatabaseHas('product_price_changes', ['product_id' => $product->id, 'source' => 'purchase', 'reference' => $purchase->reference]);
    }

    public function test_ordered_purchase_changes_nothing_until_received(): void
    {
        $product = $this->createProduct('Router');

        $this->actingAs($this->admin)
            ->post('/admin/stock/purchases', $this->purchase($product, 'ordered', [
                'quantity' => 6,
                'unit_cost' => 50000,
                'selling_price' => 80000,
            ]))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(0, $product->stock_quantity);
        $this->assertSame(0.0, (float) $product->price);

        $purchase = DB::table('stock_purchases')->first();
        $item = DB::table('stock_purchase_items')->first();

        $this->actingAs($this->admin)
            ->post('/admin/stock/purchases/'.$purchase->id.'/receive', [
                'received_date' => now()->toDateString(),
                'items' => [['purchase_item_id' => $item->id, 'quantity' => 6]],
            ])
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(6, $product->stock_quantity);
        $this->assertSame(80000.0, (float) $product->price);
        $this->assertNull($product->sale_price);
        $this->assertSame('received', DB::table('stock_purchases')->value('status'));
    }

    public function test_purchase_needs_a_selling_price_when_product_has_none(): void
    {
        $product = $this->createProduct('Speaker');

        $this->actingAs($this->admin)
            ->post('/admin/stock/purchases', $this->purchase($product, 'received', ['quantity' => 1, 'unit_cost' => 10000]))
            ->assertSessionHasErrors('items.0.selling_price');

        $this->assertSame(0, DB::table('stock_purchases')->count());
    }

    public function test_update_price_records_each_change(): void
    {
        $product = $this->createProduct('Charger');
        $this->actingAs($this->admin)->post('/admin/products/'.$product->slug.'/opening-stock', [
            'lines' => [['quantity' => 5, 'unit_cost' => 10000, 'selling_price' => 20000]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post('/admin/products/'.$product->slug.'/price', [
                'selling_price' => 22000,
                'discount_type' => 'amount',
                'discount_value' => 2000,
                'notes' => 'Weekend offer',
            ])
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(22000.0, (float) $product->price);
        $this->assertSame(20000.0, (float) $product->sale_price);
        $this->assertSame(5, $product->stock_quantity);
        $this->assertDatabaseHas('product_price_changes', ['product_id' => $product->id, 'source' => 'manual', 'old_price' => 20000, 'new_price' => 22000, 'notes' => 'Weekend offer']);

        $this->actingAs($this->admin)
            ->post('/admin/products/'.$product->slug.'/price', ['selling_price' => 22000, 'discount_type' => 'amount', 'discount_value' => 30000])
            ->assertSessionHasErrors('discount_value');
    }

    public function test_historical_setup_has_been_removed(): void
    {
        $this->actingAs($this->admin)->get('/admin/historical-data')->assertNotFound();
    }

    private function details(array $overrides = []): array
    {
        return [
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'description' => 'A product used in tests.',
            'low_stock_threshold' => 2,
            'track_stock' => true,
            'condition' => 'new',
            'status' => 'draft',
            'featured' => false,
            'merchant_sync_enabled' => false,
            ...$overrides,
        ];
    }

    private function createProduct(string $name): Product
    {
        $this->actingAs($this->admin)->post('/admin/products', $this->details(['name' => $name]))->assertSessionHasNoErrors();

        return Product::query()->where('name', $name)->firstOrFail();
    }

    private function purchase(Product $product, string $status, array $line): array
    {
        return [
            'one_time_supplier_name' => 'Kariakoo Wholesale',
            'purchase_date' => now()->toDateString(),
            'status' => $status,
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, ...$line]],
        ];
    }
}
