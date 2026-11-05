<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmartProductEntryTest extends TestCase
{
    use RefreshDatabase;

    // A valid EAN-13 (check digit 1).
    private const BARCODE = '8806095467481';

    private User $admin;

    private Category $phones;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Smart Admin',
            'email' => 'smart-admin@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);
        $this->phones = Category::create(['name' => 'Smartphones', 'type' => 'product', 'is_active' => true]);
        Brand::create(['name' => 'Samsung', 'is_active' => true]);

        config([
            'services.openai.api_key' => null,
            'services.openai.model' => 'gpt-5.4-mini',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.upcitemdb.enabled' => true,
            'services.upcitemdb.key' => null,
        ]);
    }

    public function test_barcode_lookup_uses_the_barcode_database_and_matches_brand_and_category(): void
    {
        Http::fake([
            'api.upcitemdb.com/*' => Http::response([
                'code' => 'OK',
                'total' => 1,
                'items' => [[
                    'title' => 'Samsung Galaxy A15 128GB Blue',
                    'brand' => 'SAMSUNG',
                    'model' => 'SM-A155F',
                    'color' => 'Blue',
                    'category' => 'Electronics > Cell Phones > Smartphones',
                    'description' => '<p>6.5-inch display smartphone.</p>',
                    'images' => ['https://images.example.com/a15.jpg'],
                    'offers' => [],
                ]],
            ]),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/admin/products/lookup/barcode', ['gtin' => self::BARCODE])
            ->assertOk()
            ->assertJsonPath('source', 'barcode_database')
            ->assertJsonPath('name', 'Samsung Galaxy A15 128GB Blue')
            ->assertJsonPath('gtin', self::BARCODE)
            ->assertJsonPath('model', 'SM-A155F')
            ->assertJsonPath('description', '6.5-inch display smartphone.')
            ->assertJsonPath('brand_id', Brand::query()->value('id'))
            ->assertJsonPath('category_id', $this->phones->id)
            ->assertJsonPath('existing_product', null);

        $this->assertContains(['label' => 'Color', 'value' => 'Blue'], $response->json('specifications'));
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.upcitemdb.com/prod/trial/lookup') && $request['upc'] === self::BARCODE);
    }

    public function test_barcode_lookup_warns_when_barcode_already_belongs_to_a_product(): void
    {
        Http::fake(['api.upcitemdb.com/*' => Http::response(['code' => 'OK', 'total' => 1, 'items' => [['title' => 'Galaxy A15']]])]);
        $product = Product::create([
            'category_id' => $this->phones->id,
            'name' => 'Galaxy A15 existing',
            'sku' => 'A15-EXIST',
            'description' => 'Existing.',
            'price' => 1,
            'gtin' => self::BARCODE,
            'condition' => 'new',
            'status' => 'draft',
            'merchant_sync_enabled' => false,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/admin/products/lookup/barcode', ['gtin' => self::BARCODE])
            ->assertOk()
            ->assertJsonPath('existing_product.id', $product->id);
    }

    public function test_invalid_barcode_is_rejected_without_any_lookup(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->postJson('/admin/products/lookup/barcode', ['gtin' => '8806095467480'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'not a valid barcode'));

        Http::assertNothingSent();
    }

    public function test_unknown_barcode_without_ai_explains_what_to_do(): void
    {
        Http::fake(['api.upcitemdb.com/*' => Http::response(['code' => 'OK', 'total' => 0, 'items' => []])]);

        $this->actingAs($this->admin)
            ->postJson('/admin/products/lookup/barcode', ['gtin' => self::BARCODE])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'No product details were found') && str_contains($message, 'OPENAI_API_KEY'));
    }

    public function test_unknown_barcode_falls_back_to_ai_web_search_in_tanzania(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fake([
            'api.upcitemdb.com/*' => Http::response(['code' => 'NOT_FOUND'], 404),
            'api.openai.com/*' => Http::response($this->openAiAnswer($this->productAnswer(['name' => 'Tecno Spark 20 Pro 256GB', 'brand' => 'Tecno', 'category' => 'Smartphones']))),
        ]);

        $this->actingAs($this->admin)
            ->postJson('/admin/products/lookup/barcode', ['gtin' => self::BARCODE])
            ->assertOk()
            ->assertJsonPath('source', 'web_search')
            ->assertJsonPath('name', 'Tecno Spark 20 Pro 256GB')
            ->assertJsonPath('category_id', $this->phones->id)
            ->assertJsonPath('brand_id', null)
            ->assertJsonPath('sources.0.url', 'https://jiji.co.tz/listing-1');

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'api.openai.com')) {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['tools'][0]['type'] === 'web_search'
                && $request['tools'][0]['user_location']['country'] === 'TZ'
                && $request['reasoning']['effort'] === 'low'
                && $request['text']['format']['type'] === 'json_schema';
        });
    }

    public function test_photo_recognition_sends_the_image_to_ai_and_returns_a_suggestion(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fake(['api.openai.com/*' => Http::response($this->openAiAnswer($this->productAnswer(['name' => 'Samsung Galaxy A15', 'brand' => 'Samsung'])))]);

        $this->actingAs($this->admin)
            ->post('/admin/products/lookup/image', ['image' => UploadedFile::fake()->image('phone.jpg', 800, 800)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('source', 'photo')
            ->assertJsonPath('name', 'Samsung Galaxy A15')
            ->assertJsonPath('brand_id', Brand::query()->value('id'));

        Http::assertSent(fn (Request $request) => collect($request['input'][0]['content'])->contains(
            fn ($part) => $part['type'] === 'input_image' && str_starts_with($part['image_url'], 'data:image/jpeg;base64,')
        ));
    }

    public function test_photo_recognition_needs_ai_to_be_configured(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->post('/admin/products/lookup/image', ['image' => UploadedFile::fake()->image('phone.jpg', 800, 800)], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'OPENAI_API_KEY'));

        Http::assertNothingSent();
    }

    public function test_market_price_check_returns_tanzanian_listings_and_a_suggested_price(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        $product = Product::create([
            'category_id' => $this->phones->id,
            'name' => 'Galaxy A15 128GB',
            'sku' => 'A15-128',
            'description' => 'Phone.',
            'price' => 0,
            'condition' => 'new',
            'status' => 'draft',
            'merchant_sync_enabled' => false,
            'brand_id' => Brand::query()->value('id'),
        ]);

        Http::fake(['api.openai.com/*' => Http::response($this->openAiAnswer([
            'listings' => [
                ['seller' => 'Dodoma Phones', 'title' => 'Samsung A15 128GB', 'price_tzs' => 455000, 'location' => 'Dodoma', 'condition' => 'new', 'url' => 'https://jiji.co.tz/listing-1'],
                ['seller' => 'Kariakoo Mobile', 'title' => 'Galaxy A15 128GB', 'price_tzs' => 430000, 'location' => 'Dar es Salaam', 'condition' => 'new', 'url' => 'https://shop.example.co.tz/a15'],
                ['seller' => 'City Shop', 'title' => 'Galaxy A15', 'price_tzs' => 480000, 'location' => 'Arusha', 'condition' => 'new', 'url' => 'https://city.example.co.tz/a15'],
                // Dropped: no link to check.
                ['seller' => 'Unknown', 'title' => 'A15', 'price_tzs' => 100000, 'location' => '', 'condition' => 'new', 'url' => ''],
            ],
            'low' => 1, 'median' => 1, 'high' => 1,
            'suggested_price' => 452300,
            'notes' => '',
        ]))]);

        $first = $this->actingAs($this->admin)
            ->postJson('/admin/market-price', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonCount(3, 'listings')
            ->assertJsonPath('listings.0.price', 430000.0)
            ->assertJsonPath('low', 430000.0)
            ->assertJsonPath('high', 480000.0)
            ->assertJsonPath('median', 455000.0)
            ->assertJsonPath('suggested_price', 450000.0);

        $this->assertStringContainsString('Galaxy A15 128GB', $first->json('query'));
        $this->assertStringContainsString('Samsung', $first->json('query'));

        // Cached: a second check does not search again.
        $this->actingAs($this->admin)->postJson('/admin/market-price', ['product_id' => $product->id])->assertOk();
        Http::assertSentCount(1);
    }

    public function test_market_price_check_needs_ai(): void
    {
        $product = Product::create([
            'category_id' => $this->phones->id,
            'name' => 'Router',
            'sku' => 'RT-1',
            'description' => 'Router.',
            'price' => 0,
            'condition' => 'new',
            'status' => 'draft',
            'merchant_sync_enabled' => false,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/admin/market-price', ['product_id' => $product->id])
            ->assertStatus(422);
    }

    public function test_add_stock_page_posts_opening_stock_for_several_products(): void
    {
        $first = $this->draftProduct('Charger');
        $second = $this->draftProduct('Earphones');

        $this->actingAs($this->admin)
            ->get('/admin/stock/entry')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Stock/Entry')->has('products', 2));

        $this->actingAs($this->admin)
            ->post('/admin/stock/opening', [
                'lines' => [
                    ['product_id' => $first->id, 'quantity' => 4, 'unit_cost' => 8000, 'selling_price' => 15000],
                    ['product_id' => $second->id, 'quantity' => 9, 'unit_cost' => 5000, 'selling_price' => 10000, 'discount_type' => 'percent', 'discount_value' => 10],
                ],
                'publish' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $first->fresh()->stock_quantity);
        $this->assertSame('active', $first->fresh()->status);
        $this->assertSame(9, $second->fresh()->stock_quantity);
        $this->assertSame(9000.0, (float) $second->fresh()->sale_price);
        $this->assertSame(2, DB::table('stock_movements')->where('type', 'opening_stock')->count());

        $this->actingAs($this->admin)
            ->post('/admin/stock/opening', [
                'lines' => [
                    ['product_id' => $first->id, 'quantity' => 1, 'unit_cost' => 1, 'selling_price' => 2],
                ],
            ])
            ->assertSessionHasErrors('lines');
    }

    private function draftProduct(string $name): Product
    {
        $this->actingAs($this->admin)->post('/admin/products', [
            'category_id' => $this->phones->id,
            'name' => $name,
            'description' => $name.' description.',
            'low_stock_threshold' => 2,
            'track_stock' => true,
            'condition' => 'new',
            'status' => 'draft',
            'merchant_sync_enabled' => false,
        ])->assertSessionHasNoErrors();

        return Product::query()->where('name', $name)->firstOrFail();
    }

    private function productAnswer(array $overrides = []): array
    {
        return [
            'found' => true,
            'confidence' => 'high',
            'name' => 'Product',
            'brand' => '',
            'model' => '',
            'gtin' => '',
            'category' => '',
            'short_description' => 'A short description.',
            'description' => 'A longer description.',
            'specifications' => [['label' => 'Storage', 'value' => '128 GB']],
            'color' => '',
            'image_urls' => [],
            'notes' => '',
            ...$overrides,
        ];
    }

    private function openAiAnswer(array $json): array
    {
        return [
            'id' => 'resp_test',
            'output' => [
                ['type' => 'web_search_call', 'status' => 'completed', 'action' => ['type' => 'search', 'sources' => [['type' => 'url', 'url' => 'https://jiji.co.tz/listing-1', 'title' => 'Jiji listing']]]],
                ['type' => 'message', 'role' => 'assistant', 'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode($json),
                    'annotations' => [['type' => 'url_citation', 'url' => 'https://jiji.co.tz/listing-1', 'title' => 'Jiji listing', 'start_index' => 0, 'end_index' => 1]],
                ]]],
            ],
        ];
    }
}
