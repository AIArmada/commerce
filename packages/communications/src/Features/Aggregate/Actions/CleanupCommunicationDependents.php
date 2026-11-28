<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Features\Aggregate\Api\CommunicationDependentsCleaner;
use AIArmada\Communications\Features\Delivery\Api\DeliveryDependentsCleaner;
use AIArmada\Communications\Models\Communication;

final readonly class CleanupCommunicationDependents implements CommunicationDependentsCleaner
{
    public function __construct(private DeliveryDependentsCleaner $deliveries) {}

    public function cleanup(string $communicationId): void
    {
        (new Communication)->getConnection()->transaction(function () use ($communicationId): void {
            $communication = Communication::query()->whereKey($communicationId)->lockForUpdate()->firstOrFail();

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
            }

            $this->deliveries->cleanupCommunication($communicationId);
            $communication->children()->update(['parent_id' => null]);
            $communication->recipients()->delete();
            $communication->contents()->delete();
            $communication->references()->delete();
            $communication->attachments()->delete();
        });
    }
}
