<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CancelCommunicationDeliveryAction
{
    public function handle(string $deliveryId): CommunicationDelivery
    {
        return DB::transaction(function () use ($deliveryId): CommunicationDelivery {
            $delivery = CommunicationDelivery::query()->lockForUpdate()->findOrFail($deliveryId);

            if (CommunicationDelivery::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
            }

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
