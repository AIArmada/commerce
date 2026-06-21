<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Models\CommunicationAttempt;
use AIArmada\Communications\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;

final class StartDeliveryAttemptAction
{
    public function handle(string $deliveryId, array $requestPayload = []): CommunicationAttempt
    {
        $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);

        $attemptNumber = $delivery->attempt_count + 1;

        $attempt = new CommunicationAttempt;
        $attempt->delivery_id = $delivery->id;
        $attempt->attempt_number = $attemptNumber;
        $attempt->provider = $delivery->provider;
        $attempt->request_payload = $requestPayload;
        $attempt->started_at = CarbonImmutable::now();
        $attempt->save();

        $delivery->attempt_count = $attemptNumber;
        $delivery->last_attempt_at = CarbonImmutable::now();
        $delivery->save();

        return $attempt;
    }
}
