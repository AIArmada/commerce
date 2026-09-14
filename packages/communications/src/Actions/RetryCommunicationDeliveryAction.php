<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Models\CommunicationAttempt;
use AIArmada\Communications\Models\CommunicationDelivery;
use RuntimeException;

final class RetryCommunicationDeliveryAction
{
    public function __construct(
        private readonly StartDeliveryAttemptAction $startAttempt,
    ) {}

    public function handle(string $deliveryId, array $requestPayload = []): CommunicationAttempt
    {
        $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);

        if (CommunicationDelivery::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
        }

        if ($delivery->status->value !== 'failed') {
            throw new RuntimeException("Cannot retry delivery {$deliveryId}: status is {$delivery->status->value}, expected 'failed'.");
        }

        if ($delivery->attempt_count >= $delivery->max_attempts) {
            throw new RuntimeException(
                "Cannot retry delivery {$deliveryId}: max attempts ({$delivery->max_attempts}) reached.",
            );
        }

        return $this->startAttempt->handle($deliveryId, $requestPayload);
    }
}
