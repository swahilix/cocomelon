<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_coupon_discounts_only_matching_cart_items(): void
    {
        [$accessories, $phones, $prinx, $otherBrand] = $this->catalog();
        $charger = $this->product($accessories, $prinx, 'USB-C Charger', 'COUPON-CHARGER', 100000);
        $phone = $this->product($phones, $otherBrand, 'Smartphone', 'COUPON-PHONE', 50000);

        $coupon = Coupon::create([
            'name' => 'Accessories 10%',
            'code' => 'ACCESS10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'applies_to' => 'category',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);
        $coupon->categories()->attach($accessories->id);

        $this->post('/cart/'.$charger->slug, ['quantity' => 1])->assertRedirect();
        $this->post('/cart/'.$phone->slug, ['quantity' => 1])->assertRedirect();
        $this->post('/cart/coupon', ['code' => 'access10'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Coupon applied successfully.');

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('cart.subtotal', 150000.0)
            ->where('cart.discount', 10000.0)
            ->where('cart.total', 140000.0)
            ->where('cart.coupon.code', 'ACCESS10'));
    }

    public function test_product_brand_and_order_amount_coupon_rules_are_enforced(): void
    {
        [$accessories, $phones, $prinx, $otherBrand] = $this->catalog();
        $charger = $this->product($accessories, $prinx, 'USB-C Charger', 'RULE-CHARGER', 100000);
        $phone = $this->product($phones, $otherBrand, 'Smartphone', 'RULE-PHONE', 50000);

        $productCoupon = Coupon::create([
            'name' => 'Phone fixed discount',
            'code' => 'PHONE5K',
            'discount_type' => 'fixed',
            'discount_value' => 5000,
            'applies_to' => 'product',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);
        $productCoupon->products()->attach($phone->id);

        $brandCoupon = Coupon::create([
            'name' => 'Prinx 20%',
            'code' => 'PRINX20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'applies_to' => 'brand',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);
        $brandCoupon->brands()->attach($prinx->id);

        Coupon::create([
            'name' => 'Orders over 200K',
            'code' => 'ORDER20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'applies_to' => 'order_amount',
            'minimum_order_amount' => 200000,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->post('/cart/'.$charger->slug, ['quantity' => 1]);
        $this->post('/cart/'.$phone->slug, ['quantity' => 1]);

        $this->post('/cart/coupon', ['code' => 'PHONE5K'])->assertSessionHas('success');
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('cart.discount', 5000.0));

        $this->post('/cart/coupon', ['code' => 'PRINX20'])->assertSessionHas('success');
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('cart.discount', 20000.0));

        $this->post('/cart/coupon', ['code' => 'ORDER20'])
            ->assertSessionHas('error', 'Your order has not reached the minimum amount required for this coupon.');

        $this->patch('/cart/'.$charger->slug, ['quantity' => 2]);
        $this->post('/cart/coupon', ['code' => 'ORDER20'])->assertSessionHas('success');
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('cart.subtotal', 250000.0)
            ->where('cart.discount', 50000.0)
            ->where('cart.total', 200000.0));
    }

    public function test_expired_and_not_yet_active_coupons_are_rejected(): void
    {
        [$accessories, , $prinx] = $this->catalog();
        $product = $this->product($accessories, $prinx, 'Cable', 'TIME-CABLE', 20000);

        Coupon::create([
            'name' => 'Old deal',
            'code' => 'OLD10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'applies_to' => 'all',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);

        Coupon::create([
            'name' => 'Future deal',
            'code' => 'FUTURE10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'applies_to' => 'all',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addDays(2),
            'is_active' => true,
        ]);

        $this->post('/cart/'.$product->slug, ['quantity' => 1]);
        $this->post('/cart/coupon', ['code' => 'OLD10'])
            ->assertSessionHas('error', 'This coupon has expired.')
            ->assertSessionMissing('cart_coupon_code');
        $this->post('/cart/coupon', ['code' => 'FUTURE10'])
            ->assertSessionHas('error', 'This coupon is not active yet.')
            ->assertSessionMissing('cart_coupon_code');
    }

    public function test_applied_coupon_is_saved_on_checkout_and_reduces_order_total(): void
    {
        [$accessories, , $prinx] = $this->catalog();
        $product = $this->product($accessories, $prinx, 'Power Bank', 'CHECKOUT-POWER', 100000, 5000);

        $coupon = Coupon::create([
            'name' => 'Checkout 10%',
            'code' => 'CHECK10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'applies_to' => 'all',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->post('/cart/'.$product->slug, ['quantity' => 2]);
        $this->post('/cart/coupon', ['code' => 'CHECK10'])->assertSessionHas('success');

        $response = $this->post('/checkout', [
            'customer_name' => 'Coupon Customer',
            'email' => 'coupon@example.test',
            'phone' => '+255700000001',
            'address_line_1' => 'Test Street',
            'address_line_2' => null,
            'city' => 'Dodoma',
            'region' => 'Dodoma',
            'notes' => null,
            'payment_method' => 'cash_on_delivery',
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect('/order-success/'.$order->order_number);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'coupon_id' => $coupon->id,
            'coupon_code' => 'CHECK10',
            'subtotal' => 200000,
            'shipping_total' => 10000,
            'discount_total' => 20000,
            'grand_total' => 190000,
        ]);
        $this->assertSame(1, $coupon->fresh()->usage_count);
    }

    private function catalog(): array
    {
        $accessories = Category::create(['name' => 'Accessories', 'type' => 'product', 'is_active' => true]);
        $phones = Category::create(['name' => 'Phones', 'type' => 'product', 'is_active' => true]);
        $prinx = Brand::create(['name' => 'Prinx', 'is_active' => true]);
        $otherBrand = Brand::create(['name' => 'Other', 'is_active' => true]);

        return [$accessories, $phones, $prinx, $otherBrand];
    }

    private function product(Category $category, Brand $brand, string $name, string $sku, float $price, float $shipping = 0): Product
    {
        return Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => $name,
            'sku' => $sku,
            'description' => $name.' test product.',
            'price' => $price,
            'shipping_price' => $shipping,
            'stock_quantity' => 20,
            'track_stock' => true,
            'status' => 'active',
            'condition' => 'new',
            'primary_image' => '/assets/accessory.svg',
            'mpn' => $sku.'-MPN',
        ]);
    }
}
