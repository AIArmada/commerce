<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\ContactMethods\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;

final readonly class DeleteContactMethodAction
{
    public function execute(ContactMethod $record): bool
    {
        return $record->getConnection()->transaction(function () use ($record): bool {
            if (ContactMethod::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(ContactMethod::class, $record->getKey());
            }

            return (bool) ContactMethod::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail()->delete();
        });
    }
}
