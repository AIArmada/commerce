<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Features\Delivery\Models\CommunicationAttempt;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Models\Communication;
use RuntimeException;

final class RetryCommunicationDeliveryAction
{
    public function __construct(
        private readonly StartDeliveryAttemptAction $startAttempt,
    ) {}

    public function handle(string $deliveryId, array $requestPayload = []): CommunicationAttempt
    {
        $probe = CommunicationDelivery::query()->findOrFail($deliveryId);

        if (CommunicationDelivery::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
        }

        if (Communication::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Communication::class, $probe->communication_id);
        }

        return (new CommunicationDelivery)->getConnection()->transaction(function () use ($deliveryId, $probe, $requestPayload): CommunicationAttempt {
            $communication = Communication::query()
                ->whereKey($probe->communication_id)
                ->lockForUpdate()
                ->firstOrFail();

            $delivery = CommunicationDelivery::query()
                ->where('communication_id', $communication->id)
                ->lockForUpdate()
                ->findOrFail($deliveryId);

            if ($delivery->status->value !== 'failed') {
                throw new RuntimeException("Cannot retry delivery {$deliveryId}: status is {$delivery->status->value}, expected 'failed'.");
            }

            if ($delivery->attempt_count >= $delivery->max_attempts) {
                throw new RuntimeException(
                    "Cannot retry delivery {$deliveryId}: max attempts ({$delivery->max_attempts}) reached.",
                );
            }

            return $this->startAttempt->handle($deliveryId, $requestPayload);
        });
    }
}
