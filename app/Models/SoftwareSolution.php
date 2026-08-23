<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SoftwareSolution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'short_description', 'description', 'image', 'demo_url',
        'status', 'featured', 'sort_order', 'meta_title', 'meta_description',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SoftwareSolution $solution): void {
            $solution->slug = $solution->slug ?: Str::slug($solution->name);
            $solution->meta_title = $solution->meta_title ?: $solution->name.' | Prinx Software';
            $solution->meta_description = $solution->meta_description
                ?: Str::limit(strip_tags((string) ($solution->short_description ?: $solution->description)), 155, '');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        if (Str::startsWith($this->image, ['http://', 'https://', '/'])) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }
}
