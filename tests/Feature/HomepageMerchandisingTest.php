<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductActivityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageMerchandisingTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_uses_real_sales_activity_and_sale_pricing(): void
    {
        $category = Category::create(['name' => 'Accessories', 'type' => 'product', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Prinx', 'is_active' => true]);

        $new = $this->product($category->id, $brand->id, 'Newest Product', 'NEW-1');
        $seller = $this->product($category->id, $brand->id, 'Best Seller Product', 'BEST-1');
        $trending = $this->product($category->id, $brand->id, 'Trending Product', 'TREND-1');
        $deal = $this->product($category->id, $brand->id, 'Real Daily Deal', 'DEAL-1', [
            'price' => 200000,
            'sale_price' => 150000,
            'sale_starts_at' => now()->subHour(),
            'sale_ends_at' => now()->addDay(),
        ]);

        $user = User::factory()->create();
        $order = Order::create([
            'order_number' => 'SALE-'.uniqid(),
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'email' => $user->email,
            'phone' => '0712345678',
            'address_line_1' => 'Dodoma',
            'city' => 'Dodoma',
            'country' => 'TZ',
            'subtotal' => 300000,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 300000,
            'currency' => 'TZS',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);
        $order->items()->create([
            'product_id' => $seller->id,
            'product_name' => $seller->name,
            'sku' => $seller->sku,
            'quantity' => 3,
            'unit_price' => 100000,
            'line_total' => 300000,
        ]);

        ProductActivityEvent::create([
            'product_id' => $trending->id,
            'event_type' => 'view',
            'session_key' => hash('sha256', 'session-one'),
            'event_day' => now()->toDateString(),
        ]);
        ProductActivityEvent::create([
            'product_id' => $trending->id,
            'event_type' => 'cart_add',
            'session_key' => hash('sha256', 'session-two'),
            'event_day' => now()->toDateString(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Newest Product')
            ->assertSee('Best Seller Product')
            ->assertSee('Trending Product')
            ->assertSee('Real Daily Deal');
    }

    private function product(int $categoryId, int $brandId, string $name, string $sku, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $name,
            'sku' => $sku,
            'description' => $name.' description.',
            'price' => 100000,
            'stock_quantity' => 10,
            'status' => 'active',
            'condition' => 'new',
            'merchant_sync_enabled' => false,
            'primary_image' => '/assets/accessory.svg',
        ], $overrides));
    }
}
