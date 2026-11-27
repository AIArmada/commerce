<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\Communications\Contracts\CommunicationAuditRecorder;
use AIArmada\Communications\Features\Delivery\Api\WebhookReplay;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
        $event = CommunicationEvent::query()->find($eventId);

        if ($event === null) {
            return 'skipped';
        }

        if (! $force && $event->processed_at !== null) {
            return 'skipped';
        }

        if (! in_array($event->event, ApplyProviderEventAction::DELIVERY_EVENTS, true)) {
            return 'skipped';
        }

        /** @var CommunicationDelivery|null $delivery */
        $delivery = CommunicationDelivery::query()->find($event->delivery_id);

        if ($delivery === null) {
            return 'skipped';
        }

        try {
            $target = ApplyProviderEventAction::EVENT_STATUS_MAP[$event->event];
            $transitions = app(TransitionDeliveryAction::class);

            if ($force) {
                $transitions->handle($delivery, $target, force: true);
            } else {
                $transitions->applyProviderStatus($delivery, $target);
            }

            $event->processed_at = CarbonImmutable::now();
            $event->save();

            $this->auditRecorder->recordWebhookReplay(
                communicationId: $event->communication_id ?? 'unknown',
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
        } catch (Throwable $e) {
            $event->failed_at = CarbonImmutable::now();
            $event->failure_message = $e->getMessage();
            $event->save();

            return 'failed';
        }
    }
}
