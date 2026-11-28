<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Features\Delivery\Models\CommunicationAttempt;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;

final class RedactDeliveryPayloadAction
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handleDelivery(string $deliveryId): CommunicationDelivery
    {
        $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);

        if (CommunicationDelivery::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
        }

        if ($delivery->metadata !== null) {
            $delivery->metadata = $this->redactor->redact($delivery->metadata);
        }

        $delivery->save();

        return $delivery;
    }

    public function handleAttempt(string $attemptId): CommunicationAttempt
    {
        $attempt = CommunicationAttempt::query()->findOrFail($attemptId);

        if (CommunicationAttempt::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CommunicationAttempt::class, $attemptId);
        }

        if ($attempt->request_payload !== null) {
            $attempt->request_payload = $this->redactor->redactRequest($attempt->request_payload);
        }

        if ($attempt->response_payload !== null) {
            $attempt->response_payload = $this->redactor->redactResponse($attempt->response_payload);
        }

        $attempt->save();

        return $attempt;
    }

    public function handleEvent(string $eventId): CommunicationEvent
    {
        $event = CommunicationEvent::query()->findOrFail($eventId);

        if (CommunicationEvent::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CommunicationEvent::class, $eventId);
        }

        if ($event->payload !== null) {
            $event->payload = $this->redactor->redact($event->payload);
        }

        $event->save();

        return $event;
    }
}
