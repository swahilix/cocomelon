<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_buyer_can_submit_verified_review(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $product = $this->product();
        $order = $this->completedOrder($user, $product);

        $this->actingAs($user)
            ->post("/products/{$product->slug}/reviews", [
                'rating' => 5,
                'title' => 'Excellent',
                'body' => 'The product is original and works very well.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rating' => 5,
            'status' => 'pending',
            'is_verified_purchase' => 1,
        ]);
    }

    public function test_non_buyer_cannot_submit_review(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $product = $this->product();

        $this->actingAs($user)
            ->from("/products/{$product->slug}")
            ->post("/products/{$product->slug}/reviews", [
                'rating' => 5,
                'body' => 'Trying to review without buying this item.',
            ])
            ->assertRedirect("/products/{$product->slug}")
            ->assertSessionHas('error');

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_only_approved_reviews_are_exposed_on_product_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $product = $this->product();
        $order = $this->completedOrder($user, $product);

        ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'reviewer_name' => $user->name,
            'rating' => 5,
            'title' => 'Approved review',
            'body' => 'This approved review should be visible publicly.',
            'status' => 'approved',
            'is_verified_purchase' => true,
            'approved_at' => now(),
        ]);

        $this->get("/products/{$product->slug}")
            ->assertOk()
            ->assertSee('This approved review should be visible publicly.');
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Accessories', 'type' => 'product', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Prinx', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Review Test Product',
            'sku' => 'REVIEW-TEST-1',
            'description' => 'A product used for review feature testing.',
            'price' => 100000,
            'stock_quantity' => 5,
            'status' => 'active',
            'condition' => 'new',
            'merchant_sync_enabled' => false,
            'primary_image' => '/assets/accessory.svg',
        ]);
    }

    private function completedOrder(User $user, Product $product): Order
    {
        $order = Order::create([
            'order_number' => 'TEST-'.uniqid(),
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'email' => $user->email,
            'phone' => '0712345678',
            'address_line_1' => 'Dodoma',
            'city' => 'Dodoma',
            'country' => 'TZ',
            'subtotal' => 100000,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 100000,
            'currency' => 'TZS',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
        ]);

        return $order;
    }
}
