<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'slug', 'sku', 'short_description', 'description',
        'price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'stock_quantity', 'low_stock_threshold', 'track_stock',
        'condition', 'status', 'featured', 'merchant_sync_enabled', 'primary_image', 'gallery',
        'gtin', 'mpn', 'google_product_category', 'product_type', 'item_group_id', 'color', 'size',
        'gender', 'age_group', 'video_url', 'shipping_price', 'shipping_weight', 'warranty',
        'meta_title', 'meta_description', 'merchant_last_synced_at', 'merchant_sync_status',
        'merchant_sync_error', 'sort_order'
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2', 'sale_price' => 'decimal:2', 'shipping_price' => 'decimal:2',
            'shipping_weight' => 'decimal:3', 'stock_quantity' => 'integer', 'low_stock_threshold' => 'integer', 'track_stock' => 'boolean',
            'featured' => 'boolean', 'merchant_sync_enabled' => 'boolean', 'gallery' => 'array',
            'sale_starts_at' => 'datetime', 'sale_ends_at' => 'datetime',
            'merchant_last_synced_at' => 'datetime', 'sort_order' => 'integer'
        ];
    }

    protected $appends = ['current_price', 'image_url', 'gallery_urls', 'image_metadata', 'gallery_metadata', 'in_stock', 'merchant_ready', 'plain_description'];

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $product->slug = $product->slug ?: Str::slug($product->name);
            $product->sku = strtoupper(trim($product->sku));
            $product->meta_title = $product->meta_title ?: $product->name.' | Prinx Electronics';
            $product->meta_description = $product->meta_description ?: Str::limit(self::descriptionToPlainText((string) ($product->short_description ?: $product->description)), 155, '');
        });
    }

    public function getRouteKeyName(): string { return 'slug'; }
    public function category() { return $this->belongsTo(Category::class); }
    public function brand() { return $this->belongsTo(Brand::class); }
    public function syncLogs() { return $this->hasMany(MerchantSyncLog::class); }

    public function scopeActive(Builder $query): Builder { return $query->where('status', 'active'); }
    public function scopeMerchantEligible(Builder $query): Builder
    {
        return $query->active()
            ->where('merchant_sync_enabled', true)
            ->whereNotNull('primary_image')
            ->whereNotNull('sku')
            ->whereNotNull('name')
            ->whereNotNull('description')
            ->where('price', '>', 0)
            ->where(function (Builder $identifiers): void {
                $identifiers->whereNotNull('gtin')
                    ->orWhere(function (Builder $brandAndMpn): void {
                        $brandAndMpn->whereNotNull('brand_id')->whereNotNull('mpn');
                    });
            });
    }

    public function getCurrentPriceAttribute(): float
    {
        $now = now();
        $saleValid = $this->sale_price !== null
            && (!$this->sale_starts_at || $now->gte($this->sale_starts_at))
            && (!$this->sale_ends_at || $now->lte($this->sale_ends_at));
        return (float) ($saleValid ? $this->sale_price : $this->price);
    }

    public function getImageUrlAttribute(): string
    {
        if (!$this->primary_image) return asset('assets/accessory.svg');
        if (Str::startsWith($this->primary_image, ['http://', 'https://', '/'])) return $this->primary_image;
        return Storage::disk('public')->url($this->primary_image);
    }

    public function getGalleryUrlsAttribute(): array
    {
        return collect($this->gallery ?? [])->map(function ($image) {
            return Str::startsWith($image, ['http://', 'https://', '/']) ? $image : Storage::disk('public')->url($image);
        })->values()->all();
    }

    public function getImageMetadataAttribute(): array
    {
        $description = (string) ($this->meta_description ?: $this->short_description ?: Str::limit($this->plain_description, 180, ''));

        return [
            '@type' => 'ImageObject',
            'contentUrl' => $this->image_url,
            'url' => $this->image_url,
            'name' => $this->name,
            'caption' => $description,
            'description' => $description,
            'creditText' => config('media.credit'),
            'creator' => [
                '@type' => 'Organization',
                'name' => config('media.creator'),
                'url' => config('app.url'),
            ],
            'copyrightNotice' => config('media.copyright_notice') ?: null,
            'license' => config('media.rights_url') ?: null,
            'acquireLicensePage' => config('media.rights_url') ?: null,
        ];
    }

    public function getGalleryMetadataAttribute(): array
    {
        return collect($this->gallery_urls)->map(function (string $url, int $index): array {
            return [
                '@type' => 'ImageObject',
                'contentUrl' => $url,
                'url' => $url,
                'name' => $this->name.' product image '.($index + 2),
                'caption' => (string) ($this->meta_description ?: $this->short_description),
                'creditText' => config('media.credit'),
                'creator' => [
                    '@type' => 'Organization',
                    'name' => config('media.creator'),
                    'url' => config('app.url'),
                ],
                'copyrightNotice' => config('media.copyright_notice') ?: null,
                'license' => config('media.rights_url') ?: null,
                'acquireLicensePage' => config('media.rights_url') ?: null,
            ];
        })->values()->all();
    }

    public function getInStockAttribute(): bool
    {
        return !$this->track_stock || $this->stock_quantity > 0;
    }

    public static function descriptionToPlainText(string $description): string
    {
        $description = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $description) ?? $description;
        $description = preg_replace('/^\s*(?:[-*+•✓✔]|\d+[.)])\s+/m', '', $description) ?? $description;
        $description = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $description) ?? $description;
        $description = str_replace(['|', '`', '**', '__'], ' ', $description);
        $description = preg_replace('/\s+/', ' ', strip_tags($description)) ?? $description;

        return trim($description);
    }

    public function getPlainDescriptionAttribute(): string
    {
        return self::descriptionToPlainText((string) $this->description);
    }

    public function merchantReadinessIssues(): array
    {
        $issues = [];

        if ($this->status !== 'active') $issues[] = 'status must be Active';
        if (! $this->merchant_sync_enabled) $issues[] = 'Google Merchant sync must be enabled';
        if (blank($this->sku)) $issues[] = 'SKU is missing';
        if (blank($this->name)) $issues[] = 'product name is missing';
        if (blank($this->description)) $issues[] = 'description is missing';
        if ((float) $this->price <= 0) $issues[] = 'price must be greater than 0';
        if (blank($this->primary_image)) $issues[] = 'main image is missing';
        if (blank($this->gtin) && ! (filled($this->brand_id) && filled($this->mpn))) {
            $issues[] = 'GTIN is missing, or Brand + MPN must both be provided';
        }

        return $issues;
    }

    public function getMerchantReadyAttribute(): bool
    {
        return $this->merchantReadinessIssues() === [];
    }
}
