<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Conditions\Actions;

use AIArmada\Cart\Features\Conditions\Models\Condition;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;

final readonly class DeleteStoredCondition
{
    public function execute(Condition $condition): bool
    {
        if (Condition::ownerScopingEnabled()) {
            $condition = OwnerWriteGuard::findOrFailForOwner(
                Condition::class,
                (string) $condition->getKey(),
                includeGlobal: false,
                message: 'Condition is not accessible in the current owner scope.',
            );
        }

        return (bool) $condition->delete();
    }
}
