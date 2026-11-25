<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Webhooks;

use AIArmada\Affiliates\Contracts\AffiliateWebhookTransport;
use AIArmada\Affiliates\Data\AffiliateWebhookRequest;
use AIArmada\Affiliates\Data\AffiliateWebhookResult;
use AIArmada\CommerceSupport\Drivers\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Throwable;

final readonly class PinnedAffiliateWebhookTransport implements AffiliateWebhookTransport
{
    public function __construct(private PublicHttpUrlGuard $guard, private PinnedHttpClient $client) {}

    public function send(AffiliateWebhookRequest $request): AffiliateWebhookResult
    {
        try {
            $target = $this->guard->validate($request->endpoint);
        } catch (Throwable) {
            return new AffiliateWebhookResult(false, null, 'unsafe_destination');
        }

        $headers = array_filter($request->headers, static fn (string $key): bool => ! in_array(mb_strtolower($key), ['host', 'content-length', 'transfer-encoding', 'accept', 'content-type', 'x-affiliates-webhook-signature'], true), ARRAY_FILTER_USE_KEY);
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';

        if ($request->signature !== null && $request->signature !== '') {
            $headers['X-Affiliates-Webhook-Signature'] = $request->signature;
        }

        try {
            $response = $this->client->send(
                method: 'POST', target: $target, options: ['body' => $request->body], headers: $headers,
                connectTimeout: max(1, (int) config('affiliates.webhooks.delivery.connect_timeout_seconds', 3)),
                timeout: max(1, (int) config('affiliates.webhooks.delivery.timeout_seconds', 10)),
            );

            return new AffiliateWebhookResult($response->successful(), $response->status(), $response->successful() ? null : 'http_' . $response->status());
        } catch (Throwable $exception) {
            return new AffiliateWebhookResult(false, null, str_contains(mb_strtolower($exception->getMessage()), 'timeout') ? 'timeout' : 'transport_error');
        }
    }
}
