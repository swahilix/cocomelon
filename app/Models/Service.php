<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'short_description', 'description', 'price_from',
        'price_label', 'duration', 'image', 'icon', 'status', 'featured', 'sort_order',
        'meta_title', 'meta_description',
    ];

    protected $appends = ['image_url', 'image_metadata'];

    protected function casts(): array
    {
        return [
            'price_from' => 'decimal:2',
            'featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Service $service): void {
            $service->slug = $service->slug ?: Str::slug($service->name);
            $service->meta_title = $service->meta_title ?: $service->name.' | Prinx Electronics';
            $service->meta_description = $service->meta_description ?: Str::limit(strip_tags((string) ($service->short_description ?: $service->description)), 155, '');
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

    public function inquiries()
    {
        return $this->hasMany(ServiceInquiry::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getImageUrlAttribute(): string
    {
        if (! $this->image) {
            return asset('assets/repair.svg');
        }

        if (Str::startsWith($this->image, ['http://', 'https://', '/'])) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }
    public function getImageMetadataAttribute(): array
    {
        $description = trim((string) ($this->meta_description ?: $this->short_description ?: Str::limit(strip_tags((string) $this->description), 180, '')));

        return [
            '@type' => 'ImageObject',
            'contentUrl' => $this->image_url,
            'url' => $this->image_url,
            'name' => $this->name.' service',
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
