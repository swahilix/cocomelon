<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HeroSlide extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_type',
        'product_id',
        'service_id',
        'image_path',
        'image_alt',
        'link_url',
        'eyebrow',
        'badge',
        'headline',
        'description',
        'primary_label',
        'secondary_label',
        'is_active',
        'sort_order',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
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

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if (Str::startsWith($this->image_path, ['http://', 'https://', '/'])) {
            return $this->image_path;
        }

        return Storage::disk(config('media.disk', 'public'))->url($this->image_path);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
