<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationAttempt;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RecordProviderEventAction
{
    public function handle(
        string $provider,
        string $providerEventId,
        string $event,
        ?string $communicationId = null,
        ?string $deliveryId = null,
        ?string $attemptId = null,
        ?string $providerMessageId = null,
        ?string $occurredAt = null,
        array $payload = [],
        ?string $failureMessage = null,
    ): CommunicationEvent {
        return DB::transaction(function () use (
            $attemptId,
            $communicationId,
            $deliveryId,
            $event,
            $failureMessage,
            $occurredAt,
            $payload,
            $provider,
            $providerEventId,
            $providerMessageId,
        ): CommunicationEvent {
            $resolvedCommunicationId = null;
            $resolvedDeliveryId = $deliveryId;

            if ($deliveryId !== null) {
                $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);
                $resolvedCommunicationId = $delivery->communication_id;
            }

            if ($attemptId !== null) {
                $attempt = CommunicationAttempt::query()->findOrFail($attemptId);
                $attemptDelivery = $attempt->delivery()->firstOrFail();

                if ($resolvedDeliveryId !== null && $resolvedDeliveryId !== $attemptDelivery->id) {
                    throw new RuntimeException('Provider event attempt does not belong to the supplied delivery.');
                }

                $resolvedDeliveryId = $attemptDelivery->id;
                $resolvedCommunicationId ??= $attemptDelivery->communication_id;
            }

            if ($communicationId !== null) {
                $communication = Communication::query()->findOrFail($communicationId);

                if ($resolvedCommunicationId !== null && $resolvedCommunicationId !== $communication->id) {
                    throw new RuntimeException('Provider event references records from different communications.');
                }

                $resolvedCommunicationId = $communication->id;
            }

            if ($resolvedCommunicationId === null) {
                throw new RuntimeException('Provider event has no associated communication.');
            }

            $exists = CommunicationEvent::query()
                ->where('provider', $provider)
                ->where('provider_event_id', $providerEventId)
                ->exists();

            if ($exists) {
                throw new RuntimeException("Duplicate provider event {$provider}/{$providerEventId}.");
            }

            $eventRecord = new CommunicationEvent;
            $eventRecord->communication_id = $resolvedCommunicationId;
            $eventRecord->delivery_id = $resolvedDeliveryId;
            $eventRecord->attempt_id = $attemptId;
            $eventRecord->event = $event;
            $eventRecord->source = CommunicationEventSource::Provider;
            $eventRecord->provider = $provider;
            $eventRecord->provider_event_id = $providerEventId;
            $eventRecord->provider_message_id = $providerMessageId;
            $eventRecord->occurred_at = $occurredAt !== null ? CarbonImmutable::parse($occurredAt) : CarbonImmutable::now();
            $eventRecord->received_at = CarbonImmutable::now();
            $eventRecord->payload = $payload;
            $eventRecord->failure_message = $failureMessage;
            $eventRecord->save();

            return $eventRecord;
        });
    }
}
