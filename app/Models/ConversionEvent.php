<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'product_id',
        'order_id',
        'user_id',
        'session_key',
        'source',
        'source_detail',
        'landing_path',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'value',
        'quantity',
        'event_day',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'quantity' => 'integer',
            'event_day' => 'date',
            'metadata' => 'array',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
