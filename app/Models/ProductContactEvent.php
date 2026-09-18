<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductContactEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'channel',
        'placement',
        'session_key',
        'referrer_path',
        'user_agent_family',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
