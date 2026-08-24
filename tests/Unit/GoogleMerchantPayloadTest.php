<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\GoogleMerchantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleMerchantPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_is_transformed_into_merchant_and_local_inventory_payloads(): void
    {
        config([
            'app.url' => 'https://shop.example.test',
            'merchant.content_language' => 'en',
            'merchant.feed_label' => 'TZ',
            'merchant.currency' => 'TZS',
            'merchant.country' => 'TZ',
            'merchant.shipping_service' => 'Standard delivery',
            'merchant.default_shipping_price' => 0,
            'merchant.store_code' => 'DODOMA-01',
        ]);

        $category = Category::create(['name' => 'Phones', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Prinx', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Prinx Test Phone',
            'sku' => 'PRINX-PHONE-01',
            'description' => 'A product description suitable for Google Merchant.',
            'price' => 750000,
            'stock_quantity' => 5,
            'low_stock_threshold' => 5,
            'track_stock' => true,
            'status' => 'active',
            'condition' => 'new',
            'merchant_sync_enabled' => true,
            'primary_image' => 'https://cdn.example.test/phone.jpg',
            'gtin' => '1234567890123',
            'mpn' => 'PHONE01',
            'google_product_category' => 'Electronics > Communications > Telephony > Mobile Phones',
        ]);

        $merchant = app(GoogleMerchantService::class);
        $payload = $merchant->payload($product->load('brand'));
        $localInventory = $merchant->localInventoryPayload($product);

        $this->assertSame('PRINX-PHONE-01', $payload['offerId']);
        $this->assertSame('en', $payload['contentLanguage']);
        $this->assertSame('TZ', $payload['feedLabel']);
        $this->assertSame('IN_STOCK', $payload['productAttributes']['availability']);
        $this->assertSame('750000000000', $payload['productAttributes']['price']['amountMicros']);
        $this->assertSame(['1234567890123'], $payload['productAttributes']['gtins']);
        $this->assertSame('Prinx', $payload['productAttributes']['brand']);

        $this->assertSame('DODOMA-01', $localInventory['storeCode']);
        $this->assertSame('5', $localInventory['localInventoryAttributes']['quantity']);
        $this->assertSame('LIMITED_AVAILABILITY', $localInventory['localInventoryAttributes']['availability']);
        $this->assertSame('750000000000', $localInventory['localInventoryAttributes']['price']['amountMicros']);
    }
}
