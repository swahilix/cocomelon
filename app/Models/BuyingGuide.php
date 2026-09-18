<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BuyingGuide extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'cover_image',
        'related_product_ids',
        'status',
        'featured',
        'meta_title',
        'meta_description',
        'published_at',
    ];

    protected $appends = ['cover_image_url'];

    protected function casts(): array
    {
        return [
            'related_product_ids' => 'array',
            'featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BuyingGuide $guide): void {
            $guide->slug = $guide->slug ?: Str::slug($guide->title);
            $guide->meta_title = $guide->meta_title ?: $guide->title.' | Prinx Tech Buying Guide';
            $guide->meta_description = $guide->meta_description
                ?: Str::limit(trim((string) ($guide->excerpt ?: $guide->body)), 160, '');

            if ($guide->status === 'published' && ! $guide->published_at) {
                $guide->published_at = now();
            }

            if ($guide->status !== 'published') {
                $guide->published_at = null;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        if (Str::startsWith($this->cover_image, ['http://', 'https://', '/'])) {
            return $this->cover_image;
        }

        return Storage::disk('public')->url($this->cover_image);
    }
}
