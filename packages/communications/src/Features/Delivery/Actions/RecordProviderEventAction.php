<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Features\Delivery\Models\CommunicationAttempt;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

final class RecordProviderEventAction
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
    ) {}

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
        return (new CommunicationEvent)->getConnection()->transaction(function () use (
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

                if (CommunicationDelivery::ownerScopeConfig()->enabled) {
                    OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
                }

                $resolvedCommunicationId = $delivery->communication_id;
            }

            if ($attemptId !== null) {
                $attempt = CommunicationAttempt::query()->findOrFail($attemptId);

                if (CommunicationAttempt::ownerScopeConfig()->enabled) {
                    OwnerWriteGuard::findOrFailForOwner(CommunicationAttempt::class, $attemptId);
                }

                $attemptDelivery = $attempt->delivery()->firstOrFail();

                if ($resolvedDeliveryId !== null && $resolvedDeliveryId !== $attemptDelivery->id) {
                    throw new RuntimeException('Provider event attempt does not belong to the supplied delivery.');
                }

                $resolvedDeliveryId = $attemptDelivery->id;
                $resolvedCommunicationId ??= $attemptDelivery->communication_id;
            }

            if ($communicationId !== null) {
                $communication = Communication::query()->findOrFail($communicationId);

                if (Communication::ownerScopeConfig()->enabled) {
                    OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
                }

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
            $eventRecord->payload = $this->redactor->redact($payload);
            $eventRecord->failure_message = $failureMessage;

            try {
                $eventRecord->save();
            } catch (UniqueConstraintViolationException $exception) {
                throw new RuntimeException("Duplicate provider event {$provider}/{$providerEventId}.", previous: $exception);
            }

            return $eventRecord;
        });
    }
}
