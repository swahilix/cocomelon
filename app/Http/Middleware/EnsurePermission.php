<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        abort_unless($user?->is_admin, 403);
        abort_unless($permissions && $user->hasAnyPermission($permissions), 403, 'You do not have permission to perform this action.');

        return $next($request);
    }
}
