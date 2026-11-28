<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\CommunicationAuditRecorder;
use AIArmada\Communications\Features\Delivery\Api\WebhookReplay;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Throwable;

final readonly class ReplayWebhookEvent implements WebhookReplay
{
    public function __construct(
        private CommunicationAuditRecorder $auditRecorder,
    ) {}

    public function count(bool $force, ?string $provider, ?string $communicationId, ?string $deliveryId): int
    {
        return $this->query($force, $provider, $communicationId, $deliveryId)->count();
    }

    public function ids(bool $force, ?string $provider, ?string $communicationId, ?string $deliveryId, int $batchSize): iterable
    {
        foreach ($this->query($force, $provider, $communicationId, $deliveryId)->select('id')->lazyById(max(1, $batchSize)) as $event) {
            yield $event->id;
        }
    }

    /** @return Builder<CommunicationEvent> */
    private function query(bool $force, ?string $provider, ?string $communicationId, ?string $deliveryId): Builder
    {
        $query = CommunicationEvent::query()->whereNotNull('delivery_id');

        if (! $force) {
            $query->whereNull('processed_at');
        }

        if ($provider !== null && $provider !== '') {
            $query->where('provider', $provider);
        }

        if ($communicationId !== null && $communicationId !== '') {
            $query->where('communication_id', $communicationId);
        }

        if ($deliveryId !== null && $deliveryId !== '') {
            $query->where('delivery_id', $deliveryId);
        }

        return $query;
    }

    public function replay(string $eventId, bool $force): string
    {
        $candidate = CommunicationEvent::query()->find($eventId);

        if ($candidate === null || $candidate->delivery_id === null) {
            return 'skipped';
        }

        try {
            return (new CommunicationDelivery)->getConnection()->transaction(function () use ($candidate, $eventId, $force): string {
                $communicationId = $candidate->communication_id;

                if ($communicationId === null) {
                    $communicationId = CommunicationDelivery::query()
                        ->whereKey($candidate->delivery_id)
                        ->value('communication_id');
                }

                if (! is_string($communicationId) || $communicationId === '') {
                    return 'skipped';
                }

                $communication = Communication::query()->lockForUpdate()->find($communicationId);

                if ($communication === null) {
                    return 'skipped';
                }

                if (Communication::ownerScopeConfig()->enabled) {
                    OwnerWriteGuard::findOrFailForOwner(Communication::class, $communication->id);
                }

                $delivery = CommunicationDelivery::query()
                    ->where('communication_id', $communication->id)
                    ->lockForUpdate()
                    ->find($candidate->delivery_id);

                if ($delivery === null) {
                    return 'skipped';
                }

                $event = CommunicationEvent::query()->lockForUpdate()->find($eventId);

                if ($event === null || $event->delivery_id !== $delivery->id
                    || (! $force && $event->processed_at !== null)
                    || ! in_array($event->event, ApplyProviderEventAction::DELIVERY_EVENTS, true)) {
                    return 'skipped';
                }

                if (CommunicationDelivery::ownerScopeConfig()->enabled) {
                    OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $delivery->id);
                    OwnerWriteGuard::findOrFailForOwner(CommunicationEvent::class, $event->id);
                }

                if (($event->communication_id !== null && $event->communication_id !== $delivery->communication_id)
                    || $event->owner_type !== $delivery->owner_type
                    || $event->owner_id !== $delivery->owner_id) {
                    throw new RuntimeException('Webhook event does not belong to the delivery.');
                }

                $event->processed_at = CarbonImmutable::now();
                $event->failed_at = null;
                $event->failure_message = null;

                if (! $event->save()) {
                    throw new RuntimeException('Webhook event processing was cancelled.');
                }

                $target = ApplyProviderEventAction::EVENT_STATUS_MAP[$event->event];
                app(TransitionDeliveryAction::class)->applyProviderStatus($delivery, $target);

                $this->auditRecorder->recordWebhookReplay(
                    communicationId: $delivery->communication_id,
                    actorType: 'system',
                    actorId: 'replay-command',
                    reason: 'Webhook event replayed via command',
                    metadata: [
                        'event_id' => $event->id,
                        'delivery_id' => $event->delivery_id,
                        'provider' => $event->provider,
                        'event_type' => $event->event,
                        'replayed_at' => CarbonImmutable::now()->toIso8601String(),
                    ],
                );

                return 'replayed';
            });
        } catch (Throwable $e) {
            try {
                $this->recordFailure($eventId, $e);
            } catch (Throwable $failure) {
                report($failure);
            }

            return 'failed';
        }
    }

    private function recordFailure(string $eventId, Throwable $exception): void
    {
        (new CommunicationEvent)->getConnection()->transaction(function () use ($eventId, $exception): void {
            $event = CommunicationEvent::query()->lockForUpdate()->find($eventId);

            if ($event === null || $event->processed_at !== null) {
                return;
            }

            if (CommunicationEvent::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationEvent::class, $event->id);
            }

            $event->failed_at = CarbonImmutable::now();
            $event->failure_message = $exception->getMessage();

            if (! $event->save()) {
                throw new RuntimeException('Webhook replay failure recording was cancelled.', previous: $exception);
            }
        });
    }
}
