<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantCompareTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_is_kept_as_a_separate_cart_line_and_saved_on_order(): void
    {
        $product = $this->product();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PHONE-256-BLUE',
            'color' => 'Blue',
            'storage' => '256 GB',
            'price' => 1250000,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);

        $product->forceFill(['stock_quantity' => 3])->save();

        $this->post('/cart/'.$product->slug, ['quantity' => 2, 'variant_id' => $variant->id])
            ->assertRedirect();

        $this->post('/checkout', [
            'customer_name' => 'Variant Buyer',
            'email' => 'variant@example.test',
            'phone' => '+255700000000',
            'fulfillment_method' => 'delivery',
            'address_line_1' => 'Test Street',
            'city' => 'Dodoma',
            'region' => 'Dodoma',
            'payment_method' => 'mobile_money',
        ])->assertRedirect();

        $order = Order::with('items')->firstOrFail();
        $item = $order->items->firstOrFail();

        $this->assertSame($variant->id, $item->variant_id);
        $this->assertSame('PHONE-256-BLUE', $item->variant_sku);
        $this->assertStringContainsString('Blue', (string) $item->variant_name);
        $this->assertSame(1, ProductVariant::findOrFail($variant->id)->stock_quantity);
        $this->assertSame(1, Product::findOrFail($product->id)->stock_quantity);
    }

    public function test_compare_page_accepts_up_to_four_active_products(): void
    {
        $first = $this->product(['name' => 'Compare Phone A', 'sku' => 'COMPARE-A']);
        $second = $this->product(['name' => 'Compare Phone B', 'sku' => 'COMPARE-B']);

        $this->get('/compare?products='.$first->slug.','.$second->slug)
            ->assertOk()
            ->assertSee('Compare Phone A')
            ->assertSee('Compare Phone B');
    }

    private function product(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(
            ['name' => 'Smartphones'],
            ['type' => 'product', 'is_active' => true]
        );
        $brand = Brand::firstOrCreate(['name' => 'Prinx'], ['is_active' => true]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Variant Phone',
            'sku' => 'VARIANT-PHONE-'.uniqid(),
            'description' => 'A phone used to test variant-aware cart and comparison behavior.',
            'price' => 1000000,
            'stock_quantity' => 3,
            'track_stock' => true,
            'status' => 'active',
            'condition' => 'new',
            'merchant_sync_enabled' => false,
            'primary_image' => '/assets/phone.svg',
        ], $overrides));
    }
}
