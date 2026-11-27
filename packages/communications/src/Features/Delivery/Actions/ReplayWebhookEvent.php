<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\Communications\Contracts\CommunicationAuditRecorder;
use AIArmada\Communications\Features\Delivery\Api\WebhookReplay;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class ReplayWebhookEvent implements WebhookReplay
{
    public function __construct(
        private CommunicationAuditRecorder $auditRecorder,
    ) {}

    public function replay(string $eventId, bool $force): string
    {
        $event = CommunicationEvent::query()->findOrFail($eventId);

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
