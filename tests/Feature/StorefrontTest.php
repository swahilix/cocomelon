<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_active_product_pages_are_available(): void
    {
        $product = $this->product();

        $this->get('/')->assertOk();
        $this->get('/products/'.$product->slug)->assertOk();
        $this->get('/shop?search=Test')->assertOk();
    }

    public function test_draft_products_are_not_publicly_viewable(): void
    {
        $product = $this->product(['status' => 'draft']);

        $this->get('/products/'.$product->slug)->assertNotFound();
    }

    private function product(array $overrides = []): Product
    {
        $category = Category::create(['name' => 'Computers', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Prinx', 'is_active' => true]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Test Laptop',
            'sku' => 'TEST-LAPTOP-1',
            'description' => 'A complete product description for testing.',
            'price' => 1000000,
            'stock_quantity' => 5,
            'status' => 'active',
            'condition' => 'new',
            'merchant_sync_enabled' => true,
            'primary_image' => '/assets/laptop.svg',
            'mpn' => 'TEST-MPN-1',
        ], $overrides));
    }
}
