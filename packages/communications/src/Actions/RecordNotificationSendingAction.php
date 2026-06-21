<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Events\DeliverySending;
use AIArmada\Communications\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final class RecordNotificationSendingAction
{
    public function handle(string $communicationId, string $deliveryId): CommunicationDelivery
    {
        $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);

        $delivery->status = DeliveryStatus::Sending;
        $delivery->sending_at = CarbonImmutable::now();
        $delivery->save();

        Event::dispatch(new DeliverySending(
            deliveryId: $delivery->id,
            communicationId: $communicationId,
            channel: $delivery->channel,
        ));

        return $delivery;
    }
}
