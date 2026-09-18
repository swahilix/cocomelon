<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'label',
        'color',
        'storage',
        'size',
        'price',
        'sale_price',
        'stock_quantity',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $appends = ['current_price', 'image_url', 'display_name', 'in_stock'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductVariant $variant): void {
            $variant->sku = strtoupper(trim($variant->sku));
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getCurrentPriceAttribute(): float
    {
        $base = $this->price !== null
            ? (float) $this->price
            : (float) ($this->product?->current_price ?? 0);

        $sale = $this->sale_price !== null ? (float) $this->sale_price : null;

        return $sale !== null && $sale >= 0 && $sale < $base ? $sale : $base;
    }

    public function getImageUrlAttribute(): string
    {
        if (! $this->image) {
            return $this->product?->image_url ?: asset('assets/accessory.svg');
        }

        if (Str::startsWith($this->image, ['http://', 'https://', '/'])) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }

    public function getDisplayNameAttribute(): string
    {
        $parts = array_values(array_filter([
            trim((string) $this->label),
            trim((string) $this->color),
            trim((string) $this->storage),
            trim((string) $this->size),
        ]));

        return implode(' · ', array_values(array_unique($parts))) ?: $this->sku;
    }

    public function getInStockAttribute(): bool
    {
        return $this->is_active && $this->stock_quantity > 0;
    }
}
