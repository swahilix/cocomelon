<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductContactEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_product_contact_is_recorded(): void
    {
        $category = Category::create(['name' => 'Accessories', 'type' => 'product', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Prinx', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'WhatsApp Test Product',
            'sku' => 'WA-TEST-1',
            'description' => 'A product used for contact tracking tests.',
            'price' => 150000,
            'stock_quantity' => 4,
            'status' => 'active',
            'condition' => 'new',
            'merchant_sync_enabled' => false,
            'primary_image' => '/assets/accessory.svg',
        ]);

        $this->postJson("/products/{$product->slug}/contact-events", [
            'channel' => 'whatsapp',
            'placement' => 'primary',
        ])->assertCreated()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('product_contact_events', [
            'product_id' => $product->id,
            'channel' => 'whatsapp',
            'placement' => 'primary',
        ]);
    }

    public function test_inactive_product_contact_is_not_recorded(): void
    {
        $category = Category::create(['name' => 'Accessories', 'type' => 'product', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Prinx', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Draft Contact Product',
            'sku' => 'WA-DRAFT-1',
            'description' => 'Draft product.',
            'price' => 150000,
            'stock_quantity' => 4,
            'status' => 'draft',
            'condition' => 'new',
            'merchant_sync_enabled' => false,
            'primary_image' => '/assets/accessory.svg',
        ]);

        $this->postJson("/products/{$product->slug}/contact-events", [
            'channel' => 'whatsapp',
            'placement' => 'primary',
        ])->assertNotFound();

        $this->assertDatabaseCount('product_contact_events', 0);
    }
}
