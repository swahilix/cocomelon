<?php

namespace App\Services;

use App\Models\Product;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleMerchantService
{
    public function configured(): bool
    {
        return config('merchant.enabled')
            && filled(config('merchant.account_id'))
            && filled(config('merchant.data_source_id'))
            && is_file($this->credentialsPath());
    }

    public function localInventoryConfigured(): bool
    {
        return $this->configured()
            && (bool) config('merchant.local_inventory_enabled')
            && filled(config('merchant.store_code'));
    }

    public function credentialsPath(): string
    {
        $path = (string) config('merchant.credentials_path');

        return Str::startsWith($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }

    public function accessToken(): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('Google Merchant is not fully configured.');
        }

        return $this->serviceAccountAccessToken();
    }

    public function registerGcp(string $developerEmail): array
    {
        $accountId = (string) config('merchant.account_id');

        if (blank($accountId)) {
            throw new RuntimeException('Set the Merchant Account ID before registering the GCP project.');
        }

        if (! is_file($this->credentialsPath())) {
            throw new RuntimeException('Upload the Google service-account JSON before registering the GCP project.');
        }

        $url = config('merchant.base_url')."/accounts/v1/accounts/{$accountId}/developerRegistration:registerGcp";

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($this->serviceAccountAccessToken())
            ->timeout(45)
            ->retry(2, 500, throw: false)
            ->post($url, ['developerEmail' => $developerEmail]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: $response->body());
        }

        return $response->json();
    }

    public function sync(Product $product): array
    {
        if (! $product->merchant_ready) {
            throw new RuntimeException('Product is missing required Merchant attributes. Add an image and GTIN, or brand plus MPN.');
        }

        $accountId = config('merchant.account_id');
        $dataSource = "accounts/{$accountId}/dataSources/".config('merchant.data_source_id');
        $url = config('merchant.base_url')."/products/v1/accounts/{$accountId}/productInputs:insert";
        $response = $this->client()->post($url.'?'.http_build_query(['dataSource' => $dataSource]), $this->payload($product));

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: $response->body());
        }

        $result = ['product' => $response->json()];

        if ($this->localInventoryConfigured()) {
            $result['local_inventory'] = $this->syncLocalInventory($product);
        }

        return $result;
    }

    public function syncLocalInventory(Product $product): array
    {
        if (! $this->localInventoryConfigured()) {
            throw new RuntimeException('Google Merchant local inventory is not configured. Enable it and set the Business Profile store code.');
        }

        $accountId = (string) config('merchant.account_id');
        $productId = $this->encodedProductId($product);
        $parent = "accounts/{$accountId}/products/{$productId}";
        $url = config('merchant.base_url')."/inventories/v1/{$parent}/localInventories:insert";
        $response = $this->client()->post($url, $this->localInventoryPayload($product));

        if (! $response->successful()) {
            throw new RuntimeException('Local inventory sync failed: '.($response->json('error.message') ?: $response->body()));
        }

        return $response->json();
    }

    public function delete(Product $product): array
    {
        $accountId = config('merchant.account_id');
        $id = rawurlencode('online~'.config('merchant.content_language').'~'.config('merchant.feed_label').'~'.$product->sku);
        $dataSource = "accounts/{$accountId}/dataSources/".config('merchant.data_source_id');
        $url = config('merchant.base_url')."/products/v1/accounts/{$accountId}/productInputs/{$id}";
        $response = $this->client()->delete($url.'?'.http_build_query(['dataSource' => $dataSource]));

        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException($response->json('error.message') ?: $response->body());
        }

        return $response->json() ?? ['deleted' => true];
    }

    public function testConnection(): array
    {
        $accountId = config('merchant.account_id');
        $response = $this->client()->get(
            config('merchant.base_url')."/datasources/v1/accounts/{$accountId}/dataSources",
            ['pageSize' => 5]
        );

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: $response->body());
        }

        return $response->json();
    }

    public function payload(Product $product): array
    {
        $attributes = [
            'title' => Str::limit($product->name, 150, ''),
            'description' => Str::limit($product->plain_description, 5000, ''),
            'link' => route('products.show', $product),
            'imageLink' => $this->absoluteUrl($product->image_url),
            'additionalImageLinks' => collect($product->gallery_urls)->map(fn ($url) => $this->absoluteUrl($url))->take(10)->values()->all(),
            'availability' => $product->in_stock ? 'IN_STOCK' : 'OUT_OF_STOCK',
            'condition' => strtoupper($product->condition),
            'price' => $this->priceObject((float) $product->price),
            'brand' => $product->brand?->name,
            'mpn' => $product->mpn,
            'googleProductCategory' => $product->google_product_category,
            'productTypes' => $product->product_type ? [$product->product_type] : ($product->category ? ['Electronics > '.$product->category->name] : []),
            'itemGroupId' => $product->item_group_id,
            'color' => $product->color,
            'sizes' => $product->size ? [$product->size] : [],
            'gender' => $product->gender ? strtoupper($product->gender) : null,
            'ageGroup' => $product->age_group ? strtoupper($product->age_group) : null,
            'videoLinks' => $product->video_url ? [$product->video_url] : [],
        ];

        if ($product->sale_price && $product->current_price < (float) $product->price) {
            $attributes['salePrice'] = $this->priceObject((float) $product->sale_price);
        }

        if ($product->gtin) {
            $attributes['gtins'] = [$product->gtin];
        }

        $attributes['shipping'] = [[
            'country' => config('merchant.country'),
            'service' => config('merchant.shipping_service'),
            'price' => $this->priceObject((float) ($product->shipping_price ?? config('merchant.default_shipping_price'))),
        ]];

        $attributes = array_filter($attributes, fn ($value) => $value !== null && $value !== '' && $value !== []);

        return [
            'offerId' => $product->sku,
            'contentLanguage' => config('merchant.content_language'),
            'feedLabel' => config('merchant.feed_label'),
            'productAttributes' => $attributes,
        ];
    }

    public function localInventoryPayload(Product $product): array
    {
        $attributes = [
            'availability' => $this->localAvailability($product),
            'price' => $this->priceObject((float) $product->price),
        ];

        if ($product->track_stock) {
            $attributes['quantity'] = (string) max(0, (int) $product->stock_quantity);
        }

        if ($product->sale_price && $product->current_price < (float) $product->price) {
            $attributes['salePrice'] = $this->priceObject((float) $product->sale_price);
        }

        return [
            'storeCode' => (string) config('merchant.store_code'),
            'localInventoryAttributes' => $attributes,
        ];
    }

    protected function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->accessToken())
            ->timeout(45)
            ->retry(2, 500, throw: false);
    }

    protected function serviceAccountAccessToken(): string
    {
        return Cache::remember('google-merchant-access-token', now()->addMinutes(50), function (): string {
            $key = json_decode((string) file_get_contents($this->credentialsPath()), true, 512, JSON_THROW_ON_ERROR);
            $credentials = new ServiceAccountCredentials([config('merchant.scope')], $key);
            $token = $credentials->fetchAuthToken();

            if (empty($token['access_token'])) {
                throw new RuntimeException('Google did not return an access token.');
            }

            return $token['access_token'];
        });
    }

    protected function priceObject(float $amount): array
    {
        return [
            'amountMicros' => (string) round($amount * 1000000),
            'currencyCode' => config('merchant.currency'),
        ];
    }

    protected function localAvailability(Product $product): string
    {
        if (! $product->track_stock) {
            return 'IN_STOCK';
        }

        $quantity = max(0, (int) $product->stock_quantity);

        if ($quantity === 0) {
            return 'OUT_OF_STOCK';
        }

        if ($quantity <= max(0, (int) $product->low_stock_threshold)) {
            return 'LIMITED_AVAILABILITY';
        }

        return 'IN_STOCK';
    }

    protected function encodedProductId(Product $product): string
    {
        $identifier = implode('~', [
            config('merchant.content_language'),
            config('merchant.feed_label'),
            $product->sku,
        ]);

        return rtrim(strtr(base64_encode($identifier), '+/', '-_'), '=');
    }

    protected function absoluteUrl(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }
}
