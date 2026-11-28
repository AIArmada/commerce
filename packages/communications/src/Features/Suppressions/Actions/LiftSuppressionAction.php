<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Suppressions\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Events\SuppressionLifted;
use AIArmada\Communications\Features\Suppressions\Models\CommunicationSuppression;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class LiftSuppressionAction
{
    public function handle(string $suppressionId): CommunicationSuppression
    {
        $suppression = (new CommunicationSuppression)->getConnection()->transaction(function () use ($suppressionId): CommunicationSuppression {
            $suppression = CommunicationSuppression::query()->lockForUpdate()->findOrFail($suppressionId);

            if (CommunicationSuppression::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationSuppression::class, $suppressionId);
            }

            if ($suppression->lifted_at !== null) {
                throw new RuntimeException("Suppression {$suppressionId} is already lifted.");
            }

            $suppression->lifted_at = CarbonImmutable::now();
            $suppression->save();

            return $suppression;
        });

        Event::dispatch(new SuppressionLifted(
            suppressionId: $suppression->id,
            reason: $suppression->reason->value,
            destinationHash: $suppression->destination_hash ?? '',
        ));

        return $suppression;
    }
}
