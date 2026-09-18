<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FulfillmentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_exposes_truthful_low_stock_message(): void
    {
        $category = Category::create(['name' => 'Accessories', 'type' => 'product', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Prinx', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Low Stock Product',
            'sku' => 'LOW-STOCK-1',
            'description' => 'Low stock test.',
            'price' => 100000,
            'stock_quantity' => 2,
            'low_stock_threshold' => 3,
            'track_stock' => true,
            'status' => 'active',
            'condition' => 'new',
            'merchant_sync_enabled' => false,
            'primary_image' => '/assets/accessory.svg',
        ]);

        $this->assertTrue($product->fresh()->low_stock);
        $this->assertSame('Only 2 left in stock', $product->fresh()->stock_message);
    }

    public function test_fulfillment_settings_columns_are_available(): void
    {
        DB::table('business_settings')->where('id', 1)->update([
            'delivery_enabled' => true,
            'delivery_area' => 'Dodoma',
            'delivery_eta' => '20–40 minutes',
            'pickup_enabled' => true,
            'pickup_location' => 'Store counter',
        ]);

        $settings = DB::table('business_settings')->where('id', 1)->first();

        $this->assertSame('Dodoma', $settings->delivery_area);
        $this->assertSame('Store counter', $settings->pickup_location);
    }
}
