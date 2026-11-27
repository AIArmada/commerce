<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Events\DeliverySent;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class RecordNotificationSentAction
{
    public function handle(
        string $communicationId,
        string $deliveryId,
        ?string $providerMessageId = null,
    ): CommunicationDelivery {
        $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);

        if (CommunicationDelivery::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
        }

        if ($delivery->communication_id !== $communicationId) {
            throw new RuntimeException('Delivery does not belong to the supplied communication.');
        }

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
