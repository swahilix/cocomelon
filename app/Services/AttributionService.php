<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class AttributionService
{
    private const SESSION_KEY = 'storefront_attribution';
    private const COOKIE_KEY = 'prinx_attribution';

    public function capture(Request $request): array
    {
        $current = $this->current($request);
        $detected = $this->detect($request);

        if ($current === [] || (($current['source'] ?? 'direct') === 'direct' && $detected['source'] !== 'direct')) {
            $current = $detected;
        }

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $current);
        }

        Cookie::queue(
            self::COOKIE_KEY,
            json_encode($current, JSON_UNESCAPED_SLASHES),
            60 * 24 * 30,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        );

        return $current;
    }

    public function current(Request $request): array
    {
        $session = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
        if (is_array($session) && filled($session['source'] ?? null)) {
            return $session;
        }

        $cookie = $request->cookie(self::COOKIE_KEY);
        if (is_string($cookie) && $cookie !== '') {
            $decoded = json_decode($cookie, true);
            if (is_array($decoded) && filled($decoded['source'] ?? null)) {
                return $decoded;
            }
        }

        return [];
    }

    public function sessionKey(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $id = (string) $request->session()->getId();

        return $id !== '' ? hash('sha256', $id) : null;
    }

    private function detect(Request $request): array
    {
        $utmSource = Str::limit(trim((string) $request->query('utm_source')), 190, '');
        $utmMedium = Str::limit(trim((string) $request->query('utm_medium')), 190, '');
        $utmCampaign = Str::limit(trim((string) $request->query('utm_campaign')), 190, '');
        $referrerHost = strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
        $requestHost = strtolower((string) $request->getHost());
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        $explicitSource = $utmSource !== ''
            ? $utmSource
            : ($request->filled('gclid') ? 'google' : ($request->filled('ttclid') ? 'tiktok' : ($request->filled('igshid') ? 'instagram' : '')));

        if ($explicitSource !== '') {
            [$source, $detail] = $this->classify($explicitSource);
        } elseif ($referrerHost !== '' && ! in_array($referrerHost, array_filter([$requestHost, $appHost]), true)) {
            [$source, $detail] = $this->classify($referrerHost);
        } else {
            $source = 'direct';
            $detail = null;
        }

        return [
            'source' => $source,
            'source_detail' => $detail,
            'landing_path' => Str::limit('/'.ltrim($request->path(), '/'), 500, ''),
            'utm_source' => $utmSource !== '' ? $utmSource : null,
            'utm_medium' => $utmMedium !== '' ? $utmMedium : null,
            'utm_campaign' => $utmCampaign !== '' ? $utmCampaign : null,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    private function classify(string $value): array
    {
        $needle = strtolower(trim($value));

        if (str_contains($needle, 'google') || $needle === 'gads') {
            return ['google', $value];
        }

        if (str_contains($needle, 'instagram') || in_array($needle, ['ig', 'insta'], true)) {
            return ['instagram', $value];
        }

        if (str_contains($needle, 'tiktok') || in_array($needle, ['tt', 'tiktok_ads'], true)) {
            return ['tiktok', $value];
        }

        if (str_contains($needle, 'whatsapp') || str_contains($needle, 'wa.me') || in_array($needle, ['wa', 'whats_app'], true)) {
            return ['whatsapp', $value];
        }

        return ['referral', $value];
    }
}
