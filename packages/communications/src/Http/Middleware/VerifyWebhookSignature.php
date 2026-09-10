<?php

declare(strict_types=1);

namespace AIArmada\Communications\Http\Middleware;

use AIArmada\Communications\Webhooks\Contracts\ProviderWebhookRegistrar;
use Carbon\CarbonImmutable;
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

        $provider = $this->registrar->normalizeProvider($provider);

        if (! $this->registrar->supports($provider)) {
            abort(404, 'Invalid webhook provider.');
        }

        $secret = $this->registrar->getSecret($provider);

        if ($secret === null || $secret === '') {
            abort(401, 'Webhook secret is not configured.');
        }

        $timestampHeader = (string) config(
            'communications.webhooks.timestamp_header',
            'X-Webhook-Timestamp',
        );
        $timestampValue = $request->header($timestampHeader);

        if (! is_string($timestampValue) || preg_match('/^\d+$/', $timestampValue) !== 1) {
            abort(401, 'Missing or invalid webhook timestamp.');
        }

        $timestamp = (int) $timestampValue;
        $tolerance = max(0, (int) config(
            'communications.webhooks.timestamp_tolerance_seconds',
            300,
        ));

        if (abs(CarbonImmutable::now()->timestamp - $timestamp) > $tolerance) {
            abort(401, 'Webhook timestamp is outside the allowed tolerance.');
        }

        $signature = $request->header('X-Webhook-Signature');

        if (! is_string($signature) || $signature === '') {
            abort(401, 'Missing webhook signature.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook signature.');
        }

        $request->attributes->set(
            'communications.webhook_signature_validated_at',
            CarbonImmutable::createFromTimestampUTC($timestamp)->toIso8601String(),
        );

        return $next($request);
    }
}
