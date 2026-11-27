<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Features\Delivery\Api\DeliveryDependentsCleaner;
use AIArmada\Communications\Features\Delivery\Models\CommunicationAttempt;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;
use AIArmada\Communications\Features\Tracking\Api\DeliveryTrackingCleaner;
use AIArmada\Communications\Models\Communication;

final readonly class CleanupDeliveryDependents implements DeliveryDependentsCleaner
{
    public function __construct(private DeliveryTrackingCleaner $tracking) {}

    public function cleanup(string $deliveryId): void
    {
        (new CommunicationDelivery)->getConnection()->transaction(function () use ($deliveryId): void {
            $delivery = CommunicationDelivery::query()->whereKey($deliveryId)->lockForUpdate()->firstOrFail();

            if (CommunicationDelivery::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
            }

            $this->tracking->cleanup($deliveryId);
            $delivery->events()->eachById(fn (CommunicationEvent $event) => $event->delete(), 200);
            $delivery->attempts()->eachById(fn (CommunicationAttempt $attempt) => $attempt->delete(), 200);
        });
    }

    public function cleanupCommunication(string $communicationId): void
    {
        if (Communication::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
        }

        (new CommunicationDelivery)->getConnection()->transaction(function () use ($communicationId): void {
            CommunicationDelivery::query()
                ->where('communication_id', $communicationId)
                ->eachById(fn (CommunicationDelivery $delivery) => $delivery->delete(), 100);

            CommunicationEvent::query()->where('communication_id', $communicationId)->delete();
        });
    }
}
