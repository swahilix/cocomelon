<?php

namespace App\Services\ProductIntelligence;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Looks up what Tanzanian sellers are asking for a product online and
 * suggests a selling price. Results are cached for a few hours.
 */
class MarketPriceService
{
    public function __construct(private readonly OpenAiResponses $ai)
    {
    }

    public function enabled(): bool
    {
        return $this->ai->enabled();
    }

    /**
     * @return array<string, mixed>
     */
    public function check(string $name, ?string $brand = null, ?string $variant = null, string $condition = 'new', bool $refresh = false): array
    {
        $query = trim(collect([$brand && ! Str::contains(Str::lower($name), Str::lower($brand)) ? $brand : null, $name, $variant])->filter()->implode(' '));
        if ($query === '') {
            throw new RuntimeException('Choose a product first.');
        }

        $cacheKey = 'market-price:'.md5(Str::lower($query).'|'.$condition);
        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addHours(6), fn (): array => [
            ...$this->search($query, $condition),
            'query' => $query,
            'condition' => $condition,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function search(string $query, string $condition): array
    {
        $result = $this->ai->json(
            'You research current retail prices in Tanzania for an electronics shop in Dodoma (Prinx Tech). '
            .'Use web search to find sellers in Tanzania currently listing the exact product, preferring sellers in Dodoma, then Dar es Salaam and other Tanzanian cities. '
            .'Good sources include Jiji.co.tz, Tanzanian shop websites and Tanzanian social commerce pages. Ignore sellers outside Tanzania. '
            .'Only include listings for the same model and variant (capacity/size) and the same condition; skip listings you are unsure match. '
            .'Report prices in Tanzanian shillings (TZS) as plain numbers. Never invent listings or prices: every listing must have the URL where you saw it. '
            .'Then give low, median and high of the listed prices and a suggested_price: a competitive price near the median, rounded to a sensible retail figure (e.g. nearest 5,000 or 1,000 for cheap items). '
            .'If no reliable Tanzanian listings are found, return an empty listings array, 0 for the numbers, and explain briefly in notes.',
            [['type' => 'input_text', 'text' => "Product: {$query}\nCondition: {$condition}"]],
            'prinx_market_price',
            $this->schema(),
            true,
        );

        $listings = collect($result['data']['listings'] ?? [])
            ->filter(fn ($row) => (float) ($row['price_tzs'] ?? 0) > 0 && str_starts_with((string) ($row['url'] ?? ''), 'http'))
            ->map(fn ($row) => [
                'seller' => trim((string) ($row['seller'] ?? '')) ?: (string) parse_url($row['url'], PHP_URL_HOST),
                'title' => Str::limit(trim((string) ($row['title'] ?? '')), 140, ''),
                'price' => (int) round((float) $row['price_tzs']),
                'location' => trim((string) ($row['location'] ?? '')),
                'condition' => trim((string) ($row['condition'] ?? '')),
                'url' => (string) $row['url'],
            ])
            ->sortBy('price')
            ->take(12)
            ->values();

        $prices = $listings->pluck('price');

        return [
            'listings' => $listings->all(),
            // Recalculate from the listings we kept rather than trusting the model's arithmetic.
            'low' => $prices->isNotEmpty() ? (int) $prices->min() : null,
            'median' => $prices->isNotEmpty() ? (int) round((float) $prices->median()) : null,
            'high' => $prices->isNotEmpty() ? (int) $prices->max() : null,
            'suggested_price' => $prices->isNotEmpty() ? $this->suggested((float) ($result['data']['suggested_price'] ?? 0), (float) $prices->median(), (float) $prices->min(), (float) $prices->max()) : null,
            'notes' => trim((string) ($result['data']['notes'] ?? '')),
            'sources' => array_slice($result['sources'], 0, 10),
        ];
    }

    private function suggested(float $fromModel, float $median, float $low, float $high): int
    {
        // Keep the suggestion inside the observed range; fall back to the median.
        $value = $fromModel >= $low && $fromModel <= $high && $fromModel > 0 ? $fromModel : $median;
        $step = $value >= 100000 ? 5000 : 1000;

        return (int) (round($value / $step) * $step);
    }

    private function schema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'listings' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'seller' => $string,
                            'title' => $string,
                            'price_tzs' => ['type' => 'number'],
                            'location' => $string,
                            'condition' => $string,
                            'url' => $string,
                        ],
                        'required' => ['seller', 'title', 'price_tzs', 'location', 'condition', 'url'],
                    ],
                ],
                'low' => ['type' => 'number'],
                'median' => ['type' => 'number'],
                'high' => ['type' => 'number'],
                'suggested_price' => ['type' => 'number'],
                'notes' => $string,
            ],
            'required' => ['listings', 'low', 'median', 'high', 'suggested_price', 'notes'],
        ];
    }
}
