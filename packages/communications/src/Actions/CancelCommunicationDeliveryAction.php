<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use RuntimeException;

final class CancelCommunicationDeliveryAction
{
    public function handle(string $deliveryId): CommunicationDelivery
    {
        $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);

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
    }
}
