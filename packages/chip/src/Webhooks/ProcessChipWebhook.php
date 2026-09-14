<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Actions\DispatchChipWebhookAction;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Support\ChipWebhookOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use RuntimeException;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

class ProcessChipWebhook extends CommerceWebhookProcessor
{
    protected function processEvent(string $eventType, array $payload): void
    {
        $dispatchAction = app(DispatchChipWebhookAction::class);

        $owner = $this->resolveOwner($payload);
        if ((bool) config('chip.owner.enabled', false) && $owner === null) {
            throw new RuntimeException('Owner resolution failed');
        }

        $executor = function () use ($eventType, $payload, $dispatchAction): void {
            $idempotencyKey = $this->generateIdempotencyKey($eventType, $payload);
            $startTime = microtime(true);

            $webhook = $this->claimWebhookRecord($eventType, $payload, $idempotencyKey);

            if ($webhook === null && $this->deduplicationActive()) {
                return;
            }

            try {
                $dispatchAction->execute($eventType, $payload);

                $processingTime = (microtime(true) - $startTime) * 1000;
                $webhook?->markProcessed($processingTime);
            } catch (Throwable $exception) {
                $webhook?->markFailed($exception);

                throw $exception;
            }
        };

        if ($owner instanceof Model) {
            OwnerContext::withOwner($owner, $executor);

            return;
        }

        $executor();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveOwner(array $payload): ?Model
    {
        $ownerType = Arr::get($payload, '__owner_type');
        $ownerId = Arr::get($payload, '__owner_id');

        if (is_string($ownerType) && (is_string($ownerId) || is_int($ownerId))) {
            return OwnerContext::fromTypeAndId($ownerType, $ownerId);
        }

        return ChipWebhookOwnerResolver::resolveFromPayload($payload);
    }

    /**
     * Owner-qualify the provider event id so the shared
     * UNIQUE(name, event_id, event_type) claim does not collapse two owners'
     * identical provider events into one delivery. The raw provider id stays
     * in the stored payload; this column is the dedup key only.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractEventId(array $payload): ?string
    {
        $eventId = parent::extractEventId($payload);

        if ($eventId === null || ! (bool) config('chip.owner.enabled', false)) {
            return $eventId;
        }

        $ownerType = Arr::get($payload, '__owner_type');
        $ownerId = Arr::get($payload, '__owner_id');

        if (! is_string($ownerType) || (! is_string($ownerId) && ! is_int($ownerId))) {
            $ambient = OwnerContext::resolve();

            if ($ambient instanceof Model) {
                $ownerType = $ambient->getMorphClass();
                $ownerId = $ambient->getKey();
            }
        }

        if (! is_string($ownerType) || (! is_string($ownerId) && ! is_int($ownerId))) {
            return $eventId;
        }

        return hash('sha256', 'owner:' . $ownerType . '|' . (string) $ownerId . '|' . $eventId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function generateIdempotencyKey(string $eventType, array $payload): string
    {
        $components = [
            $eventType,
            (string) ($payload['id'] ?? ''),
            (string) ($payload['status'] ?? ''),
            (string) ($payload['updated_on'] ?? $payload['created_on'] ?? ''),
        ];

        $owner = OwnerContext::resolve();
        if ($owner instanceof Model) {
            $components[] = $owner->getMorphClass();
            $components[] = (string) $owner->getKey();
        }

        return hash('sha256', implode(':', $components));
    }

    private function deduplicationActive(): bool
    {
        return (bool) config('chip.webhooks.store_webhooks', true)
            && (bool) config('chip.webhooks.deduplication', true);
    }

    private function isDuplicateWebhook(string $idempotencyKey): bool
    {
        if (! config('chip.webhooks.deduplication', true)) {
            return false;
        }

        $webhook = Webhook::query()
            ->forOwner()
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
    private function claimWebhookRecord(string $eventType, array $payload, string $idempotencyKey): ?Webhook
    {
        if (! config('chip.webhooks.store_webhooks', true)) {
            return null;
        }

        if ($this->isDuplicateWebhook($idempotencyKey)) {
            return null;
        }

        try {
            return $this->storeWebhookRecord($eventType, $payload, $idempotencyKey);
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

        if (! $holder instanceof Webhook || $holder->status !== 'failed') {
            return null;
        }

        // The previous attempt failed: release its key and claim it, backing
        // off if another worker claims it first.
        $holder->forceFill(['idempotency_key' => null])->save();

        try {
            return $this->storeWebhookRecord($eventType, $payload, $idempotencyKey);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeWebhookRecord(string $eventType, array $payload, string $idempotencyKey): ?Webhook
    {
        if (! config('chip.webhooks.store_webhooks', true)) {
            return null;
        }

        $owner = OwnerContext::resolve();

        $attributes = [
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'pending',
            'verified' => true,
            'processed' => false,
            'processed_at' => null,
            'last_error' => null,
            'processing_time_ms' => null,
            'idempotency_key' => $idempotencyKey,
            'title' => 'Incoming: ' . $eventType,
            'events' => [$eventType],
            'callback' => (string) ($this->webhookCall->url ?? ''),
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
            ->whereKey($this->webhookCall->getKey())
            ->update($attributes);

        /** @var Webhook|null $webhook */
        $webhook = Webhook::query()
            ->withoutOwnerScope()
            ->whereKey($this->webhookCall->getKey())
            ->first();

        return $webhook;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function isDuplicateProcessedEvent(WebhookCall $current, array $payload, string $eventType): bool
    {
        $eventId = $payload['id'] ?? null;

        if (! is_string($eventId) || $eventId === '') {
            return false;
        }

        $ownerType = Arr::get($payload, '__owner_type');
        $ownerId = Arr::get($payload, '__owner_id');

        return WebhookCall::query()
            ->where('name', $current->name)
            ->whereKeyNot($current->getKey())
            ->whereNotNull('processed_at')
            ->where('payload->id', $eventId)
            ->where('payload->event_type', $eventType)
            ->where(function (Builder $builder) use ($ownerType, $ownerId): void {
                if (is_string($ownerType) && (is_string($ownerId) || is_int($ownerId))) {
                    $builder->where('payload->__owner_type', $ownerType)
                        ->where('payload->__owner_id', (string) $ownerId);

                    return;
                }

                $builder->whereNull('payload->__owner_type')
                    ->whereNull('payload->__owner_id');
            })
            ->exists();
    }
}
