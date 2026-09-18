<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'product_id', 'variant_id', 'product_name', 'variant_name', 'sku', 'variant_sku', 'quantity', 'unit_price', 'unit_cost', 'line_total'];
    protected $hidden = ['unit_cost'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed(); }
}
