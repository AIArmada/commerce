<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Events\DeliveryFailed;
use AIArmada\Communications\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final class RecordNotificationFailureAction
{
    public function handle(
        string $communicationId,
        string $deliveryId,
        string $failureMessage,
        ?string $failureCode = null,
    ): CommunicationDelivery {
        $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);

        $delivery->status = DeliveryStatus::Failed;
        $delivery->failed_at = CarbonImmutable::now();
        $delivery->failure_message = $failureMessage;
        $delivery->failure_code = $failureCode;
        $delivery->save();

        Event::dispatch(new DeliveryFailed(
            deliveryId: $delivery->id,
            communicationId: $communicationId,
            failureCode: $failureCode,
            failureMessage: $failureMessage,
        ));

        return $delivery;
    }
}
