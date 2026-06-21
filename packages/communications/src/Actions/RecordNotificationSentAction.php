<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Events\DeliverySent;
use AIArmada\Communications\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final class RecordNotificationSentAction
{
    public function handle(
        string $communicationId,
        string $deliveryId,
        ?string $providerMessageId = null,
    ): CommunicationDelivery {
        $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);

        $delivery->status = DeliveryStatus::Sent;
        $delivery->sent_at = CarbonImmutable::now();
        $delivery->provider_message_id = $providerMessageId ?? $delivery->provider_message_id;
        $delivery->save();

        Event::dispatch(new DeliverySent(
            deliveryId: $delivery->id,
            communicationId: $communicationId,
            channel: $delivery->channel,
        ));

        return $delivery;
    }
}
