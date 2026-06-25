<?php

declare(strict_types=1);

namespace AIArmada\Communications\Http\Middleware;

use AIArmada\Communications\Webhooks\Contracts\ProviderWebhookRegistrar;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWebhookSignature
{
    public function __construct(
        private readonly ProviderWebhookRegistrar $registrar,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $provider = $request->route('provider');

        if (! is_string($provider)) {
            abort(401, 'Invalid webhook provider.');
        }

        $secret = $this->registrar->getSecret($provider);

        if ($secret === null || $secret === '') {
            abort(401, 'Webhook secret is not configured.');
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
}
