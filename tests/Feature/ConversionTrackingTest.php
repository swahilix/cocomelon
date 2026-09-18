<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ConversionEvent;
use App\Models\Order;
use App\Models\Product;
use App\Services\ConversionTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversionTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_instagram_source_is_preserved_from_product_view_to_cart_and_order(): void
    {
        $product = $this->product();

        $this->get('/products/'.$product->slug.'?utm_source=instagram&utm_medium=social&utm_campaign=launch')
            ->assertOk();

        $this->post('/cart/'.$product->slug, ['quantity' => 1])->assertRedirect();
        $this->get('/checkout')->assertOk();

        $response = $this->post('/checkout', [
            'customer_name' => 'Source Test',
            'email' => 'source@example.test',
            'phone' => '+255700000000',
            'fulfillment_method' => 'delivery',
            'address_line_1' => 'Test Street',
            'city' => 'Dodoma',
            'region' => 'Dodoma',
            'payment_method' => 'cash_on_delivery',
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect('/order-success/'.$order->order_number);

        $this->assertSame('instagram', $order->acquisition_source);
        $this->assertSame('launch', $order->utm_campaign);

        $this->assertDatabaseHas('conversion_events', [
            'event_type' => 'view_product',
            'product_id' => $product->id,
            'source' => 'instagram',
            'utm_campaign' => 'launch',
        ]);
        $this->assertDatabaseHas('conversion_events', [
            'event_type' => 'add_to_cart',
            'product_id' => $product->id,
            'source' => 'instagram',
        ]);
        $this->assertDatabaseHas('conversion_events', [
            'event_type' => 'checkout_start',
            'source' => 'instagram',
        ]);
    }

    public function test_buy_now_and_whatsapp_are_recorded_as_first_party_conversion_events(): void
    {
        $product = $this->product();

        $this->get('/products/'.$product->slug.'?utm_source=google&utm_campaign=search_test')->assertOk();

        $this->post('/cart/'.$product->slug, [
            'quantity' => 1,
            'intent' => 'buy_now',
        ])->assertRedirect();

        $this->postJson('/products/'.$product->slug.'/contact-events', [
            'channel' => 'whatsapp',
            'placement' => 'primary',
        ])->assertCreated();

        $this->assertDatabaseHas('conversion_events', [
            'event_type' => 'buy_now',
            'product_id' => $product->id,
            'source' => 'google',
        ]);
        $this->assertDatabaseHas('conversion_events', [
            'event_type' => 'whatsapp_enquiry',
            'product_id' => $product->id,
            'source' => 'google',
        ]);
    }

    public function test_completed_order_conversion_is_written_only_once(): void
    {
        $product = $this->product();
        $this->get('/products/'.$product->slug.'?utm_source=tiktok&utm_campaign=video_one')->assertOk();
        $this->post('/cart/'.$product->slug, ['quantity' => 1]);

        $this->post('/checkout', [
            'customer_name' => 'Completed Test',
            'email' => 'completed@example.test',
            'phone' => '+255700000000',
            'fulfillment_method' => 'delivery',
            'address_line_1' => 'Test Street',
            'city' => 'Dodoma',
            'region' => 'Dodoma',
            'payment_method' => 'cash_on_delivery',
        ])->assertRedirect();

        $order = Order::firstOrFail();
        $order->forceFill(['status' => 'completed'])->save();

        $tracker = app(ConversionTrackingService::class);
        $tracker->recordCompletedOrder($order);
        $tracker->recordCompletedOrder($order);

        $this->assertSame(1, ConversionEvent::where('event_type', 'order_completed')->where('order_id', $order->id)->count());
        $event = ConversionEvent::where('event_type', 'order_completed')->where('order_id', $order->id)->firstOrFail();
        $this->assertSame('tiktok', $event->source);
        $this->assertSame('video_one', $event->utm_campaign);
        $this->assertSame((float) $order->grand_total, (float) $event->value);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Analytics Products', 'type' => 'product', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Prinx', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Analytics Test Product',
            'sku' => 'ANALYTICS-TEST-1',
            'description' => 'A product used to test the first-party conversion funnel.',
            'price' => 100000,
            'stock_quantity' => 5,
            'track_stock' => true,
            'status' => 'active',
            'condition' => 'new',
            'merchant_sync_enabled' => false,
            'primary_image' => '/assets/accessory.svg',
        ]);
    }
}
