<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

        // Rate limiting for failed attempts by IP
        $ip = $request->ip();
        $rateLimitKey = "affiliates:api:auth:failed:{$ip}";
        $maxAttempts = 5;
        $decayMinutes = 15;

        $attempts = (int) Cache::get($rateLimitKey, 0);

        if ($attempts >= $maxAttempts) {
            Log::warning('Affiliate API rate limit exceeded', [
                'ip' => $ip,
                'attempts' => $attempts,
            ]);

            return response()->json([
                'message' => 'Too many authentication attempts. Please try again later.',
            ], 429);
        }

        if ($token && hash_equals($token, (string) $provided)) {
            // Clear failed attempts on success
            Cache::forget($rateLimitKey);

            return $next($request);
        }

        // Increment failed attempts
        Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes($decayMinutes));

        Log::warning('Affiliate API unauthorized access attempt', [
            'ip' => $ip,
            'attempts' => $attempts + 1,
        ]);

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
