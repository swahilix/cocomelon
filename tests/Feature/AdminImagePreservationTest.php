<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminImagePreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_update_without_new_images_keeps_existing_media(): void
    {
        $admin = $this->admin();
        $category = Category::create([
            'name' => 'Phones',
            'type' => 'product',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Existing Phone',
            'sku' => 'IMG-PRODUCT-01',
            'description' => 'Existing product description.',
            'price' => 100000,
            'stock_quantity' => 3,
            'low_stock_threshold' => 1,
            'track_stock' => true,
            'condition' => 'new',
            'status' => 'active',
            'merchant_sync_enabled' => false,
            'primary_image' => 'products/existing-product.avif',
            'gallery' => ['products/gallery/existing-gallery.avif'],
        ]);

        $this->actingAs($admin)->patch('/admin/products/'.$product->slug, [
            'category_id' => $category->id,
            'name' => 'Updated Phone Name',
            'slug' => $product->slug,
            'sku' => $product->sku,
            'description' => 'Updated product description.',
            'price' => 100000,
            'sale_price' => null,
            'stock_quantity' => 3,
            'low_stock_threshold' => 1,
            'track_stock' => true,
            'condition' => 'new',
            'status' => 'active',
            'featured' => false,
            'merchant_sync_enabled' => false,
            'primary_image' => null,
            'primary_image_url' => null,
            'gallery' => null,
            'gallery_urls' => null,
        ])->assertRedirect();

        $product->refresh();
        $this->assertSame('products/existing-product.avif', $product->primary_image);
        $this->assertSame(['products/gallery/existing-gallery.avif'], $product->gallery);
    }

    public function test_service_update_without_new_image_keeps_existing_image(): void
    {
        $admin = $this->admin('service-admin@example.test');
        $category = Category::create([
            'name' => 'Repairs',
            'type' => 'service',
            'is_active' => true,
        ]);

        $service = Service::create([
            'category_id' => $category->id,
            'name' => 'Phone Repair',
            'description' => 'Repair service.',
            'image' => 'services/existing-service.webp',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->patch('/admin/services/'.$service->slug, [
            'category_id' => $category->id,
            'name' => 'Phone Repair Updated',
            'slug' => $service->slug,
            'description' => 'Updated repair service.',
            'image' => null,
            'image_url' => null,
            'status' => 'active',
            'featured' => false,
            'sort_order' => 0,
        ])->assertRedirect();

        $this->assertSame('services/existing-service.webp', $service->fresh()->image);
    }

    public function test_category_update_without_new_image_keeps_existing_image(): void
    {
        $admin = $this->admin('catalog-admin@example.test');
        $category = Category::create([
            'name' => 'Accessories',
            'type' => 'product',
            'description' => 'Accessories category.',
            'image' => 'categories/existing-category.webp',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch('/admin/catalog/categories/'.$category->id, [
            'name' => 'Accessories',
            'slug' => $category->slug,
            'type' => 'product',
            'description' => 'Updated category description.',
            'image' => null,
            'image_url' => null,
            'is_active' => true,
            'sort_order' => 0,
        ])->assertRedirect();

        $this->assertSame('categories/existing-category.webp', $category->fresh()->image);
    }

    private function admin(string $email = 'admin@example.test'): User
    {
        return User::create([
            'name' => 'Administrator',
            'email' => $email,
            'password' => 'Password123',
            'is_admin' => true,
        ]);
    }
}
