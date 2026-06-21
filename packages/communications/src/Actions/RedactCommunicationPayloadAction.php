<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Models\CommunicationAttempt;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;

final class RedactCommunicationPayloadAction
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handleContent(string $contentId): CommunicationContent
    {
        $content = CommunicationContent::query()->findOrFail($contentId);

        if ($content->payload !== null) {
            $content->payload = $this->redactor->redact($content->payload);
        }

        $content->save();

        return $content;
    }

    public function handleDelivery(string $deliveryId): CommunicationDelivery
    {
        $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);

        if ($delivery->metadata !== null) {
            $delivery->metadata = $this->redactor->redact($delivery->metadata);
        }

        $delivery->save();

        return $delivery;
    }

    public function handleAttempt(string $attemptId): CommunicationAttempt
    {
        $attempt = CommunicationAttempt::query()->findOrFail($attemptId);

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

        if ($event->payload !== null) {
            $event->payload = $this->redactor->redact($event->payload);
        }

        $event->save();

        return $event;
    }
}
