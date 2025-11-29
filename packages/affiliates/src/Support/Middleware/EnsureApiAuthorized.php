<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureApiAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = config('affiliates.api.auth', 'token');

        if ($mode === 'none') {
            return $next($request);
        }

        $token = config('affiliates.api.token');
        $provided = $request->bearerToken();

        if ($token && hash_equals($token, (string) $provided)) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
