<?php

namespace App\Services;

use App\Models\ConversionEvent;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ConversionTrackingService
{
    private const EVENTS = [
        'view_product',
        'add_to_cart',
        'buy_now',
        'checkout_start',
        'whatsapp_enquiry',
        'order_completed',
    ];

    public function __construct(private readonly AttributionService $attribution)
    {
    }

    public function record(
        Request $request,
        string $eventType,
        ?Product $product = null,
        array $metadata = [],
        ?float $value = null,
        ?int $quantity = null,
    ): void {
        if (! in_array($eventType, self::EVENTS, true) || ! Schema::hasTable('conversion_events')) {
            return;
        }

        $attribution = $this->attribution->capture($request);
        $sessionKey = $this->attribution->sessionKey($request);
        $day = now()->toDateString();

        $identity = [
            'event_type' => $eventType,
            'product_id' => $product?->id,
            'session_key' => $sessionKey,
            'event_day' => $day,
        ];

        $event = ConversionEvent::query()->firstOrNew($identity);
        $event->fill([
            'user_id' => $request->user()?->id,
            'source' => $attribution['source'] ?? 'direct',
            'source_detail' => $attribution['source_detail'] ?? null,
            'landing_path' => $attribution['landing_path'] ?? null,
            'utm_source' => $attribution['utm_source'] ?? null,
            'utm_medium' => $attribution['utm_medium'] ?? null,
            'utm_campaign' => $attribution['utm_campaign'] ?? null,
            'value' => $value,
            'quantity' => $quantity,
            'metadata' => $metadata ?: null,
        ]);
        $event->save();
    }

    public function attributionForOrder(Request $request): array
    {
        $attribution = $this->attribution->capture($request);

        return [
            'acquisition_source' => $attribution['source'] ?? 'direct',
            'acquisition_detail' => $attribution['source_detail'] ?? null,
            'utm_source' => $attribution['utm_source'] ?? null,
            'utm_medium' => $attribution['utm_medium'] ?? null,
            'utm_campaign' => $attribution['utm_campaign'] ?? null,
            'attribution_session_key' => $this->attribution->sessionKey($request),
        ];
    }

    public function recordCompletedOrder(Order $order): void
    {
        if (! Schema::hasTable('conversion_events')) {
            return;
        }

        ConversionEvent::query()->firstOrCreate(
            [
                'event_type' => 'order_completed',
                'order_id' => $order->id,
            ],
            [
                'user_id' => $order->user_id,
                'session_key' => $order->attribution_session_key,
                'source' => $order->acquisition_source ?: 'unknown',
                'source_detail' => $order->acquisition_detail,
                'utm_source' => $order->utm_source,
                'utm_medium' => $order->utm_medium,
                'utm_campaign' => $order->utm_campaign,
                'value' => (float) $order->grand_total,
                'quantity' => (int) $order->items()->sum('quantity'),
                'event_day' => now()->toDateString(),
                'metadata' => [
                    'order_number' => $order->order_number,
                    'currency' => $order->currency,
                    'payment_method' => $order->payment_method,
                ],
            ],
        );
    }
}
