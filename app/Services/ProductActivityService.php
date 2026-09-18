<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductActivityService
{
    public function record(Request $request, Product $product, string $eventType): void
    {
        if (! in_array($eventType, ['view', 'cart_add'], true) || ! Schema::hasTable('product_activity_events')) {
            return;
        }

        $sessionId = $request->hasSession() ? (string) $request->session()->getId() : '';
        if ($sessionId === '') {
            return;
        }

        DB::table('product_activity_events')->updateOrInsert(
            [
                'product_id' => $product->id,
                'event_type' => $eventType,
                'session_key' => hash('sha256', $sessionId),
                'event_day' => now()->toDateString(),
            ],
            [
                'user_id' => $request->user()?->id,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
