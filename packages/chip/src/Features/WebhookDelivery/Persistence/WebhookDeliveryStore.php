<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\WebhookDelivery\Persistence;

use AIArmada\Chip\Features\WebhookDelivery\Models\Webhook;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

final readonly class WebhookDeliveryStore
{
    public function markProcessed(Webhook $webhook, float $processingTimeMs): void
    {
        $webhook->forceFill([
            'status' => 'processed',
            'processed' => true,
            'processed_at' => CarbonImmutable::now(),
            'processing_time_ms' => $processingTimeMs,
            'failed_at' => null,
            'last_error' => null,
        ])->save();
    }

    public function markFailed(Webhook $webhook, Throwable $exception): void
    {
        $webhook->forceFill([
            'status' => 'failed',
            'processed' => false,
            'processed_at' => null,
            'failed_at' => CarbonImmutable::now(),
            'last_error' => $exception->getMessage(),
        ])->save();
    }

    private function isDuplicateWebhook(string $idempotencyKey): bool
    {
        if (! config('chip.webhooks.deduplication', true)) {
            return false;
        }

        $webhook = Webhook::query()
            ->forOwner(OwnerContext::CURRENT, false)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $webhook !== null
            && ($webhook->processed || $webhook->status === 'processed');
    }

    /**
     * Atomically claim the delivery before dispatching it.
     *
     * Returns null when another delivery already processed (or is currently
     * processing) the same idempotency key. The UNIQUE(idempotency_key)
     * constraint is the arbiter: the loser of a concurrent claim backs off
     * instead of double-dispatching. A key held by a failed delivery is
     * released so redelivery can proceed.
     *
     * @param  array<string, mixed>  $payload
     */
    public function claim(WebhookCall $call, string $eventType, array $payload, ?string $idempotencyKey): ?Webhook
    {
        if (! config('chip.webhooks.store_webhooks', true)) {
            return null;
        }

        if ($idempotencyKey === null) {
            return $this->storeWebhookRecord($call, $eventType, $payload, null);
        }

        if ($this->isDuplicateWebhook($idempotencyKey)) {
            return null;
        }

        try {
            return $this->storeWebhookRecord($call, $eventType, $payload, $idempotencyKey);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }
        }

        $holder = Webhook::query()
            ->withoutGlobalScope('chip_webhook_calls')
            ->withoutOwnerScope()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $holder instanceof Webhook || ! in_array($holder->status, ['failed', 'superseded'], true)) {
            return null;
        }

        // The previous attempt failed: release its key and claim it, backing
        // off if another worker claims it first.
        $released = Webhook::query()
            ->forOwner(OwnerContext::CURRENT, false)
            ->whereKey($holder->getKey())
            ->whereIn('status', ['failed', 'superseded'])
            ->where('idempotency_key', $idempotencyKey)
            ->update(['idempotency_key' => null]);

        if ($released !== 1) {
            return null;
        }

        try {
            return $this->storeWebhookRecord($call, $eventType, $payload, $idempotencyKey);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeWebhookRecord(WebhookCall $call, string $eventType, array $payload, ?string $idempotencyKey): ?Webhook
    {
        if (! config('chip.webhooks.store_webhooks', true)) {
            return null;
        }

        $owner = OwnerContext::resolve();

        $attributes = [
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'processing',
            'verified' => true,
            'processed' => false,
            'processed_at' => null,
            'last_error' => null,
            'processing_time_ms' => null,
            'idempotency_key' => $idempotencyKey,
            'title' => 'Incoming: ' . $eventType,
            'events' => [$eventType],
            'callback' => (string) ($call->url ?? ''),
            'created_on' => is_numeric($payload['created_on'] ?? null) ? (int) $payload['created_on'] : time(),
            'updated_on' => is_numeric($payload['updated_on'] ?? null) ? (int) $payload['updated_on'] : time(),
        ];

        if ((bool) config('chip.owner.enabled', false) && $owner instanceof Model) {
            $attributes['owner_type'] = $owner->getMorphClass();
            $attributes['owner_id'] = (string) $owner->getKey();
        }

        Webhook::query()
            ->withoutGlobalScope('chip_webhook_calls')
            ->withoutOwnerScope()
            ->where('name', Webhook::WEBHOOK_NAME)
            ->whereKey($call->getKey())
            ->update($attributes);

        /** @var Webhook|null $webhook */
        $webhook = Webhook::query()
            ->withoutOwnerScope()
            ->whereKey($call->getKey())
            ->first();

        return $webhook;
    }
}
