<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Data\ProviderEventData;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;
use Carbon\CarbonImmutable;
use RuntimeException;

final class ApplyProviderEventAction
{
    public const EVENT_STATUS_MAP = [
        'bounce' => DeliveryStatus::Bounced,
        'complaint' => DeliveryStatus::Complained,
        'delivery' => DeliveryStatus::Delivered,
        'open' => DeliveryStatus::Opened,
        'read' => DeliveryStatus::Read,
        'click' => DeliveryStatus::Clicked,
        'send' => DeliveryStatus::Sent,
        'reject' => DeliveryStatus::Failed,
        'failed' => DeliveryStatus::Failed,
        'accept' => DeliveryStatus::Accepted,
        'suppress' => DeliveryStatus::Suppressed,
        'unsubscribe' => DeliveryStatus::Unsubscribed,
    ];

    public const EVENT_TIMESTAMP_MAP = [
        'bounce' => 'bounced_at',
        'complaint' => 'complained_at',
        'delivery' => 'delivered_at',
        'open' => 'opened_at',
        'read' => 'read_at',
        'click' => 'clicked_at',
        'send' => 'sent_at',
        'reject' => 'failed_at',
        'failed' => 'failed_at',
        'accept' => 'accepted_at',
        'suppress' => 'suppressed_at',
        'unsubscribe' => 'suppressed_at',
    ];

    public const DELIVERY_EVENTS = [
        'bounce', 'complaint', 'delivery', 'open', 'read', 'click',
        'send', 'reject', 'failed', 'accept', 'suppress', 'unsubscribe',
    ];

    public function handle(ProviderEventData $eventData): CommunicationDelivery
    {
        if ($eventData->deliveryId === null) {
            throw new RuntimeException('Provider event has no associated delivery.');
        }

        $eventType = $eventData->eventType;

        if (! in_array($eventType, self::DELIVERY_EVENTS, true)) {
            throw new RuntimeException("Unknown provider event type: {$eventType}.");
        }

        $event = $this->recordEvent($eventData);

        $delivery = CommunicationDelivery::query()->findOrFail($eventData->deliveryId);

        $delivery->status = self::EVENT_STATUS_MAP[$eventType];

        $timestampColumn = self::EVENT_TIMESTAMP_MAP[$eventType];

        if ($delivery->{$timestampColumn} === null) {
            $delivery->{$timestampColumn} = CarbonImmutable::now();
        }

        $delivery->save();

        $event->processed_at = CarbonImmutable::now();
        $event->save();

        return $delivery;
    }

    private function recordEvent(ProviderEventData $data): CommunicationEvent
    {
        if ($data->providerEventId !== null) {
            $exists = CommunicationEvent::query()
                ->where('provider', $data->provider)
                ->where('provider_event_id', $data->providerEventId)
                ->exists();

            if ($exists) {
                throw new RuntimeException("Duplicate provider event {$data->provider}/{$data->providerEventId}.");
            }
        }

        $event = new CommunicationEvent;
        $event->communication_id = $data->communicationId;
        $event->delivery_id = $data->deliveryId;
        $event->event = $data->eventType;
        $event->source = CommunicationEventSource::Provider;
        $event->provider = $data->provider;
        $event->provider_event_id = $data->providerEventId;
        $event->provider_message_id = $data->providerMessageId;
        $event->occurred_at = $data->occurredAt;
        $event->received_at = CarbonImmutable::now();
        $event->payload = $data->payload;
        $event->failure_message = $data->failureMessage;
        $event->save();

        return $event;
    }
}
