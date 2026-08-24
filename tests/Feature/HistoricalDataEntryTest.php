<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HistoricalDataEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_purchase_is_recorded_without_changing_current_stock(): void
    {
        [$admin, $product] = $this->fixtures();
        $before = (int) $product->stock_quantity;

        $this->actingAs($admin)
            ->post('/admin/historical-data/purchases', [
                'one_time_supplier_name' => 'Old Supplier',
                'invoice_number' => 'OLD-INV-001',
                'purchase_date' => now()->subDays(20)->toDateString(),
                'additional_cost' => 5000,
                'discount' => 0,
                'tax' => 0,
                'amount_paid' => 205000,
                'payment_method' => 'Cash',
                'payment_date' => now()->subDays(20)->toDateString(),
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_cost' => 20000,
                ]],
            ])
            ->assertRedirect();

        $this->assertSame($before, (int) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_purchases', [
            'invoice_number' => 'OLD-INV-001',
            'is_historical' => 1,
            'status' => 'received',
        ]);
        $this->assertDatabaseHas('stock_purchase_items', [
            'product_id' => $product->id,
            'quantity' => 10,
            'quantity_received' => 10,
            'unit_cost' => 20000,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'historical_purchase',
            'quantity' => 10,
            'affects_stock' => 0,
            'is_historical' => 1,
        ]);
    }

    public function test_historical_sales_are_locked_until_purchases_are_confirmed(): void
    {
        [$admin, $product] = $this->fixtures();
        $cashId = (int) DB::table('payment_methods')->where('code', 'cash')->value('id');

        $this->actingAs($admin)
            ->from('/admin/historical-data')
            ->post('/admin/historical-data/sales', [
                'sale_at' => now()->subDays(10)->format('Y-m-d H:i:s'),
                'payment_method_id' => $cashId,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 50000,
                    'unit_cost' => 20000,
                ]],
            ])
            ->assertRedirect('/admin/historical-data')
            ->assertSessionHasErrors('sale');

        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_historical_sale_uses_purchase_cost_does_not_reduce_stock_and_appears_in_profit_report(): void
    {
        [$admin, $product] = $this->fixtures();
        $before = (int) $product->stock_quantity;
        $purchaseDate = now()->subDays(20)->toDateString();
        $saleDate = now()->subDays(10);
        $saleAt = $saleDate->format('Y-m-d H:i:s');
        $cashId = (int) DB::table('payment_methods')->where('code', 'cash')->value('id');

        $this->actingAs($admin)->post('/admin/historical-data/purchases', [
            'one_time_supplier_name' => 'Old Supplier',
            'purchase_date' => $purchaseDate,
            'amount_paid' => 200000,
            'payment_method' => 'Cash',
            'payment_date' => $purchaseDate,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_cost' => 20000,
            ]],
        ])->assertRedirect();

        $this->actingAs($admin)
            ->post('/admin/historical-data/confirm-purchases')
            ->assertRedirect();

        $this->actingAs($admin)
            ->post('/admin/historical-data/sales', [
                'sale_at' => $saleAt,
                'customer_name' => 'Historical Customer',
                'discount' => 0,
                'amount_paid' => 50000,
                'payment_method_id' => $cashId,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 25000,
                    'unit_cost' => '',
                ]],
            ])
            ->assertRedirect();

        $this->assertSame($before, (int) $product->fresh()->stock_quantity);
        $sale = DB::table('pos_sales')->where('is_historical', true)->first();
        $this->assertNotNull($sale);
        $this->assertSame('Historical Customer', $sale->customer_name);
        $this->assertSame(50000.0, (float) $sale->total);

        $line = DB::table('pos_sale_items')->where('pos_sale_id', $sale->id)->first();
        $this->assertNotNull($line);
        $this->assertSame(2, (int) $line->quantity);
        $this->assertSame(20000.0, (float) $line->unit_cost);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'source_type' => 'pos_sale',
            'source_id' => $sale->id,
            'type' => 'historical_sale',
            'quantity' => -2,
            'affects_stock' => 0,
            'is_historical' => 1,
        ]);

        $this->actingAs($admin)
            ->get('/admin/profit?from='.$saleDate->copy()->subDay()->toDateString().'&to='.$saleDate->copy()->addDay()->toDateString().'&source=pos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Profit/Index')
                ->where('summary.pos_revenue', 50000)
                ->where('summary.cogs', 40000)
                ->where('summary.gross_profit', 10000)
            );
    }

    private function fixtures(): array
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Password123',
            'is_admin' => true,
        ]);

        $category = Category::create([
            'name' => 'Historical Test Products',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Historical Product',
            'sku' => 'HIST-001',
            'description' => 'Historical import test product.',
            'price' => 30000,
            'stock_quantity' => 7,
            'status' => 'active',
            'condition' => 'new',
            'merchant_sync_enabled' => false,
            'track_stock' => true,
        ]);

        return [$admin, $product];
    }
}
