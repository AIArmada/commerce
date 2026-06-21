<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Models\CommunicationEvent;
use Carbon\CarbonImmutable;
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
        $exists = CommunicationEvent::query()
            ->where('provider', $provider)
            ->where('provider_event_id', $providerEventId)
            ->exists();

        if ($exists) {
            throw new RuntimeException("Duplicate provider event {$provider}/{$providerEventId}.");
        }

        $eventRecord = new CommunicationEvent;
        $eventRecord->communication_id = $communicationId;
        $eventRecord->delivery_id = $deliveryId;
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
    }
}
