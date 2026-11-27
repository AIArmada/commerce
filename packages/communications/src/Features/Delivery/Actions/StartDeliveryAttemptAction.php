<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Features\Delivery\Models\CommunicationAttempt;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class StartDeliveryAttemptAction
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handle(string $deliveryId, array $requestPayload = []): CommunicationAttempt
    {
        return DB::transaction(function () use ($deliveryId, $requestPayload): CommunicationAttempt {
            $delivery = CommunicationDelivery::query()->lockForUpdate()->findOrFail($deliveryId);

            if (CommunicationDelivery::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
            }

            $attemptNumber = $delivery->attempt_count + 1;

            $attempt = new CommunicationAttempt;
            $attempt->delivery_id = $delivery->id;
            $attempt->attempt_number = $attemptNumber;
            $attempt->provider = $delivery->provider;
            $attempt->request_payload = $this->redactor->redactRequest($requestPayload);
            $attempt->started_at = CarbonImmutable::now();
            $attempt->save();

            $delivery->attempt_count = $attemptNumber;
            $delivery->last_attempt_at = CarbonImmutable::now();
            $delivery->save();

            return $attempt;
        });
    }
}
