<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\WebhookDelivery\EntryPoints\Jobs;

use AIArmada\Chip\Actions\DispatchChipWebhookAction;
use AIArmada\Chip\Features\WebhookDelivery\Persistence\WebhookDeliveryStore;
use AIArmada\Chip\Support\ChipWebhookOwnerResolver;
use AIArmada\CommerceSupport\Features\Webhooks\EntryPoints\CommerceWebhookProcessor;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use RuntimeException;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

class ProcessChipWebhook extends CommerceWebhookProcessor
{
    protected function processEvent(string $eventType, array $payload): void
    {
        $dispatchAction = app(DispatchChipWebhookAction::class);
        $store = app(WebhookDeliveryStore::class);

        $owner = $this->resolveOwner($payload);
        if ((bool) config('chip.owner.enabled', false) && $owner === null) {
            throw new RuntimeException('Owner resolution failed');
        }

        $executor = function () use ($eventType, $payload, $dispatchAction, $store): void {
            $idempotencyKey = $this->deduplicationActive() ? $this->generateIdempotencyKey($eventType, $payload) : null;
            $startTime = microtime(true);

            $webhook = $store->claim($this->webhookCall, $eventType, $payload, $idempotencyKey);

            if ($webhook === null && $this->deduplicationActive()) {
                return;
            }

            try {
                $result = $dispatchAction->execute($eventType, $payload);

                if ($result->isFailed()) {
                    throw new RuntimeException($result->message ?? 'CHIP webhook dispatch failed.');
                }

                $processingTime = (microtime(true) - $startTime) * 1000;
                if ($webhook !== null) {
                    $store->markProcessed($webhook, $processingTime);
                }
            } catch (Throwable $exception) {
                if ($webhook !== null) {
                    $store->markFailed($webhook, $exception);
                }

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
