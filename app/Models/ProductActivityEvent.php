<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductActivityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'event_type',
        'session_key',
        'event_day',
    ];

    protected function casts(): array
    {
        return ['event_day' => 'date'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
