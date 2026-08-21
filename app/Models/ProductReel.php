<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductReel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_type',
        'product_id',
        'service_id',
        'title',
        'caption',
        'media_type',
        'media_path',
        'external_url',
        'cover_image',
        'cta_label',
        'display_seconds',
        'is_active',
        'sort_order',
    ];

    protected $appends = [
        'media_url',
        'cover_url',
        'item_url',
    ];

    protected function casts(): array
    {
        return [
            'display_seconds' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getMediaUrlAttribute(): string
    {
        if (filled($this->external_url)) {
            return (string) $this->external_url;
        }

        if (filled($this->media_path)) {
            return $this->storageUrl((string) $this->media_path);
        }

        return $this->itemImage();
    }

    public function getCoverUrlAttribute(): string
    {
        if (filled($this->cover_image)) {
            return $this->storageUrl((string) $this->cover_image);
        }

        if ($this->media_type === 'image') {
            return $this->media_url;
        }

        return $this->itemImage();
    }

    public function getItemUrlAttribute(): string
    {
        if ($this->item_type === 'service' && $this->service) {
            return route('services.show', $this->service);
        }

        if ($this->product) {
            return route('products.show', $this->product);
        }

        return $this->item_type === 'service' ? route('services') : route('catalog');
    }

    private function itemImage(): string
    {
        if ($this->item_type === 'service' && $this->service) {
            return $this->service->image_url;
        }

        if ($this->product) {
            return $this->product->image_url;
        }

        return asset($this->item_type === 'service' ? 'assets/repair.svg' : 'assets/accessory.svg');
    }

    private function storageUrl(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return Storage::disk(config('media.disk', 'public'))->url($path);
    }
}
