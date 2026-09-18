<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductContactEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductContactEventController extends Controller
{
    public function store(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->status === 'active', 404);

        $validated = $request->validate([
            'channel' => ['required', 'in:whatsapp'],
            'placement' => ['nullable', 'in:primary,mobile_sticky'],
        ]);

        $agent = strtolower((string) $request->userAgent());
        $family = match (true) {
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'iphone'), str_contains($agent, 'ipad') => 'iOS',
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'macintosh') => 'macOS',
            default => 'Other',
        };

        ProductContactEvent::create([
            'product_id' => $product->id,
            'user_id' => $request->user()?->id,
            'channel' => $validated['channel'],
            'placement' => $validated['placement'] ?? null,
            'session_key' => $request->hasSession()
                ? hash('sha256', (string) $request->session()->getId())
                : null,
            'referrer_path' => Str::limit((string) parse_url((string) $request->headers->get('referer'), PHP_URL_PATH), 500, ''),
            'user_agent_family' => $family,
        ]);

        return response()->json(['ok' => true], 201);
    }
}
