<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_soft_delete_and_restore_a_product(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Phones', 'type' => 'product', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Prinx', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Test Phone',
            'sku' => 'TEST-PHONE-01',
            'description' => 'A test product description.',
            'price' => 100000,
            'stock_quantity' => 2,
            'status' => 'active',
            'condition' => 'new',
        ]);

        $this->actingAs($admin)->delete('/admin/products/'.$product->slug)->assertRedirect('/admin/products');
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->get('/products/'.$product->slug)->assertNotFound();

        $this->actingAs($admin)->post('/admin/products/'.$product->id.'/restore')->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    public function test_product_and_service_categories_have_public_landing_pages(): void
    {
        $productCategory = Category::create(['name' => 'Computers', 'type' => 'product', 'is_active' => true]);
        $serviceCategory = Category::create(['name' => 'Computer Support', 'type' => 'service', 'is_active' => true]);

        Service::create([
            'category_id' => $serviceCategory->id,
            'name' => 'Laptop Repair',
            'description' => 'Professional laptop repair.',
            'status' => 'active',
        ]);

        $this->get('/shop/category/'.$productCategory->slug)->assertOk();
        $this->get('/services/category/'.$serviceCategory->slug)->assertOk();
        $this->get('/shop?category='.$productCategory->slug)->assertOk();
        $this->get('/services?category='.$serviceCategory->slug)->assertOk();
        $this->get('/shop/category/'.$serviceCategory->slug)->assertNotFound();
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => 'Password123!',
            'is_admin' => true,
        ]);
    }
}
