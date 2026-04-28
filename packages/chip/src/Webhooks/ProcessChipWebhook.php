<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Services\WebhookEventDispatcher;
use AIArmada\Chip\Support\ChipWebhookOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use RuntimeException;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

/**
 * Process CHIP webhook events using spatie/laravel-webhook-client.
 *
 * This job handles incoming CHIP webhooks and dispatches the appropriate events
 * using the centralized WebhookEventDispatcher service.
 *
 * @property WebhookCall $webhookCall
 */
class ProcessChipWebhook extends CommerceWebhookProcessor
{
    /**
     * Process the webhook event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        $dispatcher = app(WebhookEventDispatcher::class);

        $owner = $this->resolveOwner($payload);
        if ((bool) config('chip.owner.enabled', false) && $owner === null) {
            throw new RuntimeException('Owner resolution failed');
        }

        $executor = function () use ($eventType, $payload, $dispatcher): void {
            $idempotencyKey = $this->generateIdempotencyKey($eventType, $payload);

            if ($this->isDuplicateWebhook($idempotencyKey)) {
                return;
            }

            $startTime = microtime(true);
            $webhook = $this->storeWebhookRecord($eventType, $payload, $idempotencyKey);

            try {
                WebhookReceived::dispatch(
                    $eventType,
                    $payload,
                    $dispatcher->extractPurchase($payload),
                    $dispatcher->extractPayout($payload),
                    $dispatcher->extractBillingTemplateClient($payload),
                );

                $dispatcher->dispatch($eventType, $payload);

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
     * @param  array<string, mixed>  $payload
     */
    private function storeWebhookRecord(string $eventType, array $payload, string $idempotencyKey): ?Webhook
    {
        if (! config('chip.webhooks.store_webhooks', true)) {
            return null;
        }

        return Webhook::query()
            ->forOwner()
            ->updateOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'event_type' => $eventType,
                    'event' => $eventType,
                    'payload' => $payload,
                    'status' => 'pending',
                    'verified' => true,
                    'processed' => false,
                    'processed_at' => null,
                    'last_error' => null,
                    'processing_time_ms' => null,
                    'title' => 'Incoming: ' . $eventType,
                    'events' => [$eventType],
                    'callback' => (string) ($this->webhookCall->url ?? ''),
                    'created_on' => is_numeric($payload['created_on'] ?? null) ? (int) $payload['created_on'] : time(),
                    'updated_on' => is_numeric($payload['updated_on'] ?? null) ? (int) $payload['updated_on'] : time(),
                ]
            );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function isDuplicateProcessedEvent(WebhookCall $current, array $payload, string $eventType): bool
    {
        $eventId = $this->extractEventId($payload);

        if ($eventId === null) {
            return false;
        }

        $hasExplicitType = array_key_exists('event_type', $payload)
            || array_key_exists('event', $payload)
            || array_key_exists('type', $payload);

        $ownerType = Arr::get($payload, '__owner_type');
        $ownerId = Arr::get($payload, '__owner_id');

        return WebhookCall::query()
            ->where('name', $current->name)
            ->whereKeyNot($current->getKey())
            ->whereNotNull('processed_at')
            ->where(function (Builder $builder) use ($eventId): void {
                $builder->where('payload->event_id', $eventId)
                    ->orWhere('payload->eventId', $eventId)
                    ->orWhere('payload->id', $eventId)
                    ->orWhere('payload->data->id', $eventId);
            })
            ->where(function (Builder $builder) use ($eventType, $hasExplicitType): void {
                if ($hasExplicitType) {
                    $builder->where('payload->event_type', $eventType)
                        ->orWhere('payload->event', $eventType)
                        ->orWhere('payload->type', $eventType);

                    return;
                }

                $builder->whereNull('payload->event_type')
                    ->whereNull('payload->event')
                    ->whereNull('payload->type');
            })
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
