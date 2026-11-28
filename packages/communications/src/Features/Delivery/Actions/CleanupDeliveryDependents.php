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
use RuntimeException;

final readonly class CleanupDeliveryDependents implements DeliveryDependentsCleaner
{
    public function __construct(private DeliveryTrackingCleaner $tracking) {}

    public function cleanup(string $deliveryId): void
    {
        $probe = CommunicationDelivery::query()->findOrFail($deliveryId);

        (new CommunicationDelivery)->getConnection()->transaction(function () use ($deliveryId, $probe): void {
            $communication = Communication::query()
                ->whereKey($probe->communication_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $communication->id);
            }

            $delivery = CommunicationDelivery::query()
                ->where('communication_id', $communication->id)
                ->lockForUpdate()
                ->findOrFail($deliveryId);

            if (CommunicationDelivery::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
            }

            $this->tracking->cleanup($deliveryId);
            $delivery->events()->eachById(static function (CommunicationEvent $event): void {
                if (! $event->delete()) {
                    throw new RuntimeException('Communication dependent deletion was cancelled.');
                }
            }, 200);
            $delivery->attempts()->eachById(static function (CommunicationAttempt $attempt): void {
                if (! $attempt->delete()) {
                    throw new RuntimeException('Communication dependent deletion was cancelled.');
                }
            }, 200);
        });
    }

    public function cleanupCommunication(string $communicationId): void
    {
        if (Communication::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
        }

        (new CommunicationDelivery)->getConnection()->transaction(function () use ($communicationId): void {
            $communication = Communication::query()
                ->whereKey($communicationId)
                ->lockForUpdate()
                ->firstOrFail();

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $communication->id);
            }

            CommunicationDelivery::query()
                ->where('communication_id', $communication->id)
                ->eachById(static function (CommunicationDelivery $delivery): void {
                    if (! $delivery->delete()) {
                        throw new RuntimeException('Communication dependent deletion was cancelled.');
                    }
                }, 100);

            CommunicationEvent::query()->where('communication_id', $communication->id)->delete();
        });
    }
}
