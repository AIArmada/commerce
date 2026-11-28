<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use RuntimeException;

final class CancelCommunicationDeliveryAction
{
    public function handle(string $deliveryId): CommunicationDelivery
    {
        $probe = CommunicationDelivery::query()->findOrFail($deliveryId);

        if (CommunicationDelivery::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
        }

        if (Communication::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Communication::class, $probe->communication_id);
        }

        return (new CommunicationDelivery)->getConnection()->transaction(function () use ($deliveryId, $probe): CommunicationDelivery {
            $communication = Communication::query()
                ->whereKey($probe->communication_id)
                ->lockForUpdate()
                ->firstOrFail();

            $delivery = CommunicationDelivery::query()
                ->where('communication_id', $communication->id)
                ->lockForUpdate()
                ->findOrFail($deliveryId);

            $cancellableStatuses = ['pending', 'scheduled', 'queued', 'sending'];

            if (! in_array($delivery->status->value, $cancellableStatuses, true)) {
                throw new RuntimeException(
                    "Cannot cancel delivery {$deliveryId}: status is {$delivery->status->value}.",
                );
            }

            $delivery->status = DeliveryStatus::Cancelled;
            $delivery->cancelled_at = CarbonImmutable::now();
            $delivery->save();

            return $delivery;
        });
    }
}
