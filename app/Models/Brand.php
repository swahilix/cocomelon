<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'logo', 'is_active'];

    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Brand $brand): void {
            $brand->slug = $brand->slug ?: Str::slug($brand->name);
        });
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) return null;
        if (Str::startsWith($this->logo, ['http://', 'https://', '/'])) return $this->logo;

        return Storage::disk('public')->url($this->logo);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
