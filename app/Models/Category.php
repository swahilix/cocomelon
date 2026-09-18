<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'type', 'description', 'image', 'meta_title', 'meta_description', 'is_active', 'sort_order',
    ];

    protected $appends = ['image_url', 'image_metadata'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            $category->slug = $category->slug ?: Str::slug($category->name);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeProductType(Builder $query): Builder
    {
        return $query->where('type', 'product');
    }

    public function scopeServiceType(Builder $query): Builder
    {
        return $query->where('type', 'service');
    }

    public function getImageUrlAttribute(): string
    {
        if (! $this->image) {
            return asset($this->type === 'service' ? 'assets/repair.svg' : 'assets/accessory.svg');
        }

        if (Str::startsWith($this->image, ['http://', 'https://', '/'])) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }
    public function getImageMetadataAttribute(): array
    {
        $description = trim((string) ($this->description ?: 'Browse '.$this->name.' at Prinx Tech.'));

        return [
            '@type' => 'ImageObject',
            'contentUrl' => $this->image_url,
            'url' => $this->image_url,
            'name' => $this->name.' '.$this->type.' category',
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

}
