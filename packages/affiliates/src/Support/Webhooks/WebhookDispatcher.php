<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Webhooks;

use AIArmada\Affiliates\Jobs\DispatchAffiliateWebhook;
use AIArmada\Affiliates\Models\AffiliateWebhookDelivery;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsonException;

class WebhookDispatcher
{
    /** @param array<string, mixed> $payload */
    public function dispatch(string $type, array $payload): void
    {
        if (! (bool) config('affiliates.events.dispatch_webhooks', false)) {
            return;
        }

        $eventId = (string) Str::uuid();
        $body = [
            'type' => $type,
            'id' => $eventId,
            'data' => $payload,
            'sent_at' => now()->toIso8601String(),
        ];
        $bodyJson = $this->encode($body);
        $secret = config('affiliates.webhooks.signature_secret');
        $signature = is_string($secret) && $secret !== '' ? hash_hmac('sha256', $bodyJson, $secret) : null;
        $headers = array_filter(
            (array) config('affiliates.webhooks.headers', []),
            static fn (mixed $value, string | int $key): bool => is_string($key) && is_scalar($value),
            ARRAY_FILTER_USE_BOTH,
        );
        $owner = OwnerContext::resolve();
        $seen = [];

        foreach (Arr::wrap(config("affiliates.webhooks.endpoints.{$type}", [])) as $configuredEndpoint) {
            $endpoint = mb_trim((string) $configuredEndpoint);
            $parts = parse_url($endpoint);

            if ($endpoint === '' || ! is_array($parts) || ! in_array(mb_strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
                continue;
            }

            $destinationKey = hash('sha256', mb_strtolower($endpoint));

            if (isset($seen[$destinationKey])) {
                continue;
            }

            $seen[$destinationKey] = true;
            $delivery = AffiliateWebhookDelivery::query()->create([
                'event_id' => $eventId,
                'event_type' => $type,
                'destination_key' => $destinationKey,
                'endpoint' => $endpoint,
                'headers' => $headers,
                'body_json' => $bodyJson,
                'signature' => $signature,
                'status' => 'pending',
                'max_attempts' => max(1, (int) config('affiliates.webhooks.delivery.max_attempts', 5)),
                'available_at' => now(),
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
            ]);

            DispatchAffiliateWebhook::dispatch($delivery->id)->afterCommit();
        }
    }

    /** @param array<string, mixed> $body */
    private function encode(array $body): string
    {
        try {
            return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new JsonException('Affiliate webhook payload could not be encoded.', previous: $exception);
        }
    }
}
