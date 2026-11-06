<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deals_link_shows_only_products_on_a_running_sale(): void
    {
        $category = Category::create(['name' => 'Phones', 'type' => 'product', 'is_active' => true]);
        $base = ['category_id' => $category->id, 'description' => 'Test product.', 'stock_quantity' => 5, 'status' => 'active', 'condition' => 'new'];

        Product::create([...$base, 'name' => 'Phone On Sale', 'sku' => 'DEAL-1', 'price' => 300000, 'sale_price' => 250000]);
        Product::create([...$base, 'name' => 'Phone Full Price', 'sku' => 'DEAL-2', 'price' => 300000]);
        Product::create([...$base, 'name' => 'Phone Sale Ended', 'sku' => 'DEAL-3', 'price' => 300000, 'sale_price' => 200000, 'sale_ends_at' => now()->subDay()]);

        $this->get('/shop?deals=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Catalog')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Phone On Sale')
                ->where('filters.deals', '1'));

        $this->get('/shop')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 3));
    }
}
