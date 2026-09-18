<?php

namespace App\Http\Middleware;

use App\Services\AttributionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureStorefrontAttribution
{
    public function __construct(private readonly AttributionService $attribution)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*') && $request->method() === 'GET') {
            $this->attribution->capture($request);
        }

        return $next($request);
    }
}
