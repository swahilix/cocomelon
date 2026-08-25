<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_or_change_wishlist(): void
    {
        $product = $this->product();

        $this->get('/wishlist')->assertRedirect(route('login'));
        $this->post('/wishlist/'.$product->slug)->assertRedirect(route('login'));

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_logged_in_customer_can_add_view_toggle_and_remove_wishlist_products(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'phone' => '+255712345678',
        ]);
        $product = $this->product();

        $this->actingAs($user)
            ->post('/wishlist/'.$product->slug)
            ->assertRedirect()
            ->assertSessionHas('success', 'Product added to your wishlist.');

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($user)
            ->get('/wishlist')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Wishlist')
                ->has('products', 1)
                ->where('products.0.id', $product->id)
                ->where('products.0.slug', $product->slug)
                ->where('wishlistCount', 1)
                ->where('wishlistProductIds.0', $product->id)
                ->where('wishlistProductSlugs.0', $product->slug));

        // The storefront heart uses POST as a toggle, so a second click removes it.
        $this->actingAs($user)
            ->post('/wishlist/'.$product->slug)
            ->assertSessionHas('success', 'Product removed from your wishlist.');

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($user)->post('/wishlist/'.$product->slug);
        $this->actingAs($user)
            ->delete('/wishlist/'.$product->slug)
            ->assertSessionHas('success', 'Product removed from your wishlist.');

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_customers_only_see_their_own_wishlist(): void
    {
        $first = User::factory()->create(['is_admin' => false, 'phone' => '+255712345678']);
        $second = User::factory()->create(['is_admin' => false, 'phone' => '+255713345678']);
        $product = $this->product();

        $this->actingAs($first)->post('/wishlist/'.$product->slug);

        $this->actingAs($second)
            ->get('/wishlist')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Wishlist')
                ->has('products', 0)
                ->where('wishlistCount', 0)
                ->where('wishlistProductIds', [])
                ->where('wishlistProductSlugs', []));
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Wishlist Test Phone',
            'slug' => 'wishlist-test-phone',
            'sku' => 'WL-001',
            'description' => 'A product used to verify customer wishlists.',
            'price' => 150000,
            'stock_quantity' => 5,
            'track_stock' => true,
            'status' => 'active',
        ]);
    }
}
