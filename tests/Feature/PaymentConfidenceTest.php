<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentConfidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_rejects_a_payment_method_disabled_by_store_settings(): void
    {
        DB::table('business_settings')->where('id', 1)->update([
            'payment_mobile_money_enabled' => false,
            'payment_bank_transfer_enabled' => true,
            'payment_cash_on_delivery_enabled' => false,
        ]);

        $product = $this->product();
        $this->post('/cart/'.$product->slug, ['quantity' => 1]);

        $this->from('/checkout')->post('/checkout', [
            'customer_name' => 'Payment Test',
            'email' => 'payment@example.test',
            'phone' => '+255700000000',
            'fulfillment_method' => 'delivery',
            'address_line_1' => 'Test Street',
            'city' => 'Dodoma',
            'region' => 'Dodoma',
            'payment_method' => 'mobile_money',
        ])->assertRedirect('/checkout')->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_accepts_an_enabled_payment_method(): void
    {
        DB::table('business_settings')->where('id', 1)->update([
            'payment_mobile_money_enabled' => false,
            'payment_bank_transfer_enabled' => true,
            'payment_cash_on_delivery_enabled' => false,
        ]);

        $product = $this->product();
        $this->post('/cart/'.$product->slug, ['quantity' => 1]);

        $response = $this->post('/checkout', [
            'customer_name' => 'Payment Test',
            'email' => 'payment@example.test',
            'phone' => '+255700000000',
            'fulfillment_method' => 'delivery',
            'address_line_1' => 'Test Street',
            'city' => 'Dodoma',
            'region' => 'Dodoma',
            'payment_method' => 'bank_transfer',
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect('/order-success/'.$order->order_number);
        $this->assertSame('bank_transfer', $order->payment_method);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Accessories', 'type' => 'product', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Prinx', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Payment Test Product',
            'sku' => 'PAYMENT-TEST-1',
            'description' => 'Payment confidence test product.',
            'price' => 100000,
            'stock_quantity' => 4,
            'status' => 'active',
            'condition' => 'new',
            'merchant_sync_enabled' => false,
            'primary_image' => '/assets/accessory.svg',
        ]);
    }
}
