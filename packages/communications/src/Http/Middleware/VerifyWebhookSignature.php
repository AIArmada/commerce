<?php

declare(strict_types=1);

namespace AIArmada\Communications\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $provider = $request->route('provider');

        if (! is_string($provider)) {
            abort(401, 'Invalid webhook provider.');
        }

        $secret = $this->resolveSecret($provider);

        if ($secret === null || $secret === '') {
            return $next($request);
        }

        $signature = $request->header('X-Webhook-Signature');

        if (! is_string($signature) || $signature === '') {
            abort(401, 'Missing webhook signature.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }

    private function resolveSecret(string $provider): ?string
    {
        $secret = config("services.webhooks.{$provider}.secret");

        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        $fallback = config('services.webhooks.secret');

        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        return null;
    }
}
