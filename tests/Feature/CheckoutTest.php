<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_add_a_product_and_place_an_order(): void
    {
        $category = Category::create(['name' => 'Accessories', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Prinx', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'USB-C Charger',
            'sku' => 'CHARGER-65W',
            'description' => 'A fast USB-C charger.',
            'price' => 95000,
            'shipping_price' => 5000,
            'stock_quantity' => 4,
            'status' => 'active',
            'condition' => 'new',
            'primary_image' => '/assets/accessory.svg',
            'mpn' => 'CHARGER65',
        ]);

        $this->post('/cart/'.$product->slug, ['quantity' => 2])->assertRedirect();
        $this->get('/checkout')->assertOk();

        $response = $this->post('/checkout', [
            'customer_name' => 'Test Customer',
            'email' => 'customer@example.test',
            'phone' => '+255700000000',
            'address_line_1' => '123 Test Street',
            'address_line_2' => null,
            'city' => 'Dodoma',
            'region' => 'Dodoma',
            'notes' => 'Call before delivery.',
            'payment_method' => 'cash_on_delivery',
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect('/order-success/'.$order->order_number);
        $this->assertDatabaseHas('orders', ['email' => 'customer@example.test', 'grand_total' => 200000]);
        $this->assertDatabaseHas('order_items', ['sku' => 'CHARGER-65W', 'quantity' => 2]);
        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->get('/order-success/'.$order->order_number)->assertOk();
    }
}
