<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Models\Communication;

final class DeleteCommunicationAggregateAction
{
    public function handle(string $communicationId): void
    {
        (new Communication)->getConnection()->transaction(function () use ($communicationId): void {
            $communication = Communication::query()->whereKey($communicationId)->lockForUpdate()->firstOrFail();

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
            }

            $communication->delete();
        });
    }
}
