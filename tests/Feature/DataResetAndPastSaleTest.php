<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DataResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DataResetAndPastSaleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    private array $createdBackups = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);
        $this->category = Category::create(['name' => 'Phones', 'type' => 'product', 'is_active' => true]);
        $this->createdBackups = collect(DataResetService::backups())->pluck('name')->all();
    }

    protected function tearDown(): void
    {
        // Remove backup files created by this test only.
        foreach (DataResetService::backups() as $file) {
            if (! in_array($file['name'], $this->createdBackups, true)) {
                File::delete(DataResetService::backupDirectory().DIRECTORY_SEPARATOR.$file['name']);
            }
        }

        parent::tearDown();
    }

    public function test_only_administrators_can_open_or_run_the_reset(): void
    {
        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@example.test',
            'password' => 'Password123',
            'is_admin' => true,
            'role_id' => DB::table('roles')->where('slug', 'cashier')->value('id'),
        ]);

        $this->actingAs($cashier)->get('/admin/system/reset')->assertForbidden();
        $this->actingAs($cashier)->post('/admin/system/reset/backup')->assertForbidden();
        $this->actingAs($cashier)->post('/admin/system/reset', ['confirmation' => 'RESET', 'password' => 'Password123'])->assertForbidden();

        $this->actingAs($this->admin)
            ->get('/admin/system/reset')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/System/DataReset')->where('recentBackup', null));
    }

    public function test_reset_requires_a_backup_the_word_reset_and_the_password(): void
    {
        $product = $this->productWithOpeningStock('Router', 5);

        $this->actingAs($this->admin)
            ->post('/admin/system/reset', ['confirmation' => 'RESET', 'password' => 'Password123'])
            ->assertSessionHasErrors('confirmation');

        $this->actingAs($this->admin)->post('/admin/system/reset/backup')->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post('/admin/system/reset', ['confirmation' => 'reset', 'password' => 'Password123'])
            ->assertSessionHasErrors('confirmation');

        $this->actingAs($this->admin)
            ->post('/admin/system/reset', ['confirmation' => 'RESET', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_reset_clears_transactions_and_keeps_products_customers_suppliers_and_prices(): void
    {
        $product = $this->productWithOpeningStock('Samsung A15', 10, 400000);
        $supplier = Supplier::create(['name' => 'Kariakoo Wholesale', 'is_active' => true, 'opening_balance' => 250000]);
        $customerId = DB::table('pos_customers')->insertGetId(['name' => 'Asha', 'phone' => '+255712345678', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin)->post('/admin/stock/purchases', [
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'status' => 'received',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 310000, 'selling_price' => 400000]],
        ])->assertSessionHasNoErrors();
        $this->recordPastSale($product, 2, 400000);
        $this->actingAs($this->admin)->post('/admin/purchase-orders', [
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(12, $product->fresh()->stock_quantity);
        $this->assertGreaterThan(0, DB::table('stock_movements')->count());

        $this->actingAs($this->admin)
            ->get('/admin/system/reset')
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.groups.0.rows', 2)
                ->where('preview.reset.suppliers_with_opening_balance', 1));

        $this->actingAs($this->admin)->post('/admin/system/reset/backup')->assertSessionHas('success');
        $backup = collect(DataResetService::backups())->first();
        $contents = json_decode(File::get(DataResetService::backupDirectory().DIRECTORY_SEPARATOR.$backup['name']), true);
        $this->assertCount(1, $contents['tables']['pos_sales']);
        $this->assertCount(1, $contents['tables']['stock_purchases']);
        $this->assertSame(12, (int) collect($contents['tables']['products'])->firstWhere('id', $product->id)['stock_quantity']);

        $this->actingAs($this->admin)
            ->get('/admin/system/reset/backups/'.$backup['name'])
            ->assertOk()
            ->assertDownload($backup['name']);

        $this->actingAs($this->admin)
            ->post('/admin/system/reset', ['confirmation' => 'RESET', 'password' => 'Password123'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        foreach (['pos_sales', 'pos_sale_items', 'stock_purchases', 'stock_purchase_items', 'stock_movements', 'purchase_orders', 'purchase_order_items', 'cash_register_sessions', 'expenses', 'installment_plans', 'orders'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} should be empty");
        }

        $product->refresh();
        $this->assertSame(0, $product->stock_quantity);
        $this->assertSame(0.0, (float) $product->average_cost);
        $this->assertSame(400000.0, (float) $product->price);
        $this->assertDatabaseHas('pos_customers', ['id' => $customerId, 'name' => 'Asha']);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Kariakoo Wholesale']);
        $this->assertSame(0.0, (float) $supplier->fresh()->opening_balance);
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.data_reset']);
    }

    public function test_past_sale_is_dated_reduces_stock_and_skips_the_cash_register(): void
    {
        $product = $this->productWithOpeningStock('Charger', 6, 15000);
        $date = now()->subDays(20)->toDateString();

        $this->actingAs($this->admin)
            ->get('/admin/sales/past')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Sales/PastSale')->where('products.0.price', 15000));

        $this->actingAs($this->admin)->post('/admin/sales/past', [
            'sale_date' => $date,
            'sale_time' => '15:30',
            'customer_name' => 'Juma',
            'customer_phone' => '0754000111',
            'save_customer' => true,
            'payment_method_id' => DB::table('payment_methods')->value('id'),
            'discount' => 2000,
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 15000]],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $sale = DB::table('pos_sales')->first();
        $this->assertSame($date.' 15:30:00', (string) $sale->created_at);
        $this->assertTrue((bool) $sale->is_backdated);
        $this->assertNull($sale->cash_register_session_id);
        $this->assertSame(28000.0, (float) $sale->total);
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertSame(0, DB::table('cash_register_movements')->count());
        $this->assertDatabaseHas('pos_customers', ['name' => 'Juma']);
        $this->assertDatabaseHas('stock_movements', ['type' => 'pos_sale', 'quantity' => -2, 'source_id' => $sale->id]);
    }

    public function test_past_sale_of_a_variant_reduces_that_variant(): void
    {
        $product = Product::create($this->productAttributes('iPhone 15'));
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'IP15-128', 'label' => '128 GB', 'is_active' => true, 'stock_quantity' => 0]);
        $this->actingAs($this->admin)->post('/admin/stock/opening', [
            'lines' => [['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 3, 'unit_cost' => 2000000, 'selling_price' => 2500000]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post('/admin/sales/past', [
            'sale_date' => now()->subDay()->toDateString(),
            'payment_method_id' => DB::table('payment_methods')->value('id'),
            'items' => [['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 2500000]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $variant->fresh()->stock_quantity);
        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertSame('iPhone 15 — 128 GB', DB::table('pos_sale_items')->value('product_name'));
    }

    public function test_past_sale_needs_stock_and_a_date_that_is_not_in_the_future(): void
    {
        $product = $this->productWithOpeningStock('Speaker', 1, 50000);
        $method = DB::table('payment_methods')->value('id');

        $this->actingAs($this->admin)->post('/admin/sales/past', [
            'sale_date' => now()->subDay()->toDateString(),
            'payment_method_id' => $method,
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 50000]],
        ])->assertSessionHasErrors('items');

        $this->actingAs($this->admin)->post('/admin/sales/past', [
            'sale_date' => now()->addDays(2)->toDateString(),
            'payment_method_id' => $method,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50000]],
        ])->assertSessionHasErrors('sale_date');

        $this->assertSame(0, DB::table('pos_sales')->count());
        $this->assertSame(1, $product->fresh()->stock_quantity);
    }

    private function productAttributes(string $name): array
    {
        return [
            'category_id' => $this->category->id,
            'name' => $name,
            'sku' => strtoupper(str($name)->slug()->toString()),
            'description' => $name,
            'price' => 0,
            'condition' => 'new',
            'status' => 'draft',
            'track_stock' => true,
            'merchant_sync_enabled' => false,
        ];
    }

    private function productWithOpeningStock(string $name, int $quantity, float $price = 10000): Product
    {
        $product = Product::create($this->productAttributes($name));

        $this->actingAs($this->admin)->post('/admin/stock/opening', [
            'lines' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => round($price * 0.75), 'selling_price' => $price]],
        ])->assertSessionHasNoErrors();

        return $product->fresh();
    }

    private function recordPastSale(Product $product, int $quantity, float $price): void
    {
        $this->actingAs($this->admin)->post('/admin/sales/past', [
            'sale_date' => now()->subDay()->toDateString(),
            'payment_method_id' => DB::table('payment_methods')->value('id'),
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $price]],
        ])->assertSessionHasNoErrors();
    }
}
