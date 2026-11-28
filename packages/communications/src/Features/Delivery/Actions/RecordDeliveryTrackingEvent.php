<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\TrackingInteractionType;
use AIArmada\Communications\Features\Delivery\Api\TrackingDeliveryEvents;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class RecordDeliveryTrackingEvent implements TrackingDeliveryEvents
{
    public function __construct(private PayloadRedactor $redactor) {}

    public function assertAccessible(string $deliveryId): void
    {
        $this->delivery($deliveryId);
    }

    public function record(string $deliveryId, string $interaction, ?array $metadata): string
    {
        $delivery = $this->delivery($deliveryId);
        $type = TrackingInteractionType::from($interaction);
        $now = CarbonImmutable::now();

        $event = new CommunicationEvent;
        $event->communication_id = $delivery->communication_id;
        $event->delivery_id = $delivery->id;
        $event->event = $type->value;
        $event->source = CommunicationEventSource::Tracking;
        $event->occurred_at = $now;
        $event->received_at = $now;
        $event->metadata = $metadata !== null ? $this->redactor->redact($metadata) : null;

        if (! $event->save()) {
            throw new RuntimeException('Tracking event creation was cancelled.');
        }

        return $event->id;
    }

    private function delivery(string $deliveryId): CommunicationDelivery
    {
        if (CommunicationDelivery::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
        }

        return CommunicationDelivery::query()->findOrFail($deliveryId);
    }
}
