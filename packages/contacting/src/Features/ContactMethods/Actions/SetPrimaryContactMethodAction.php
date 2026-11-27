<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\ContactMethods\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;
use RuntimeException;

final class SetPrimaryContactMethodAction
{
    public function execute(ContactMethod $contactMethod): ContactMethod
    {
        return $contactMethod->getConnection()->transaction(function () use ($contactMethod): ContactMethod {
            if (ContactMethod::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(ContactMethod::class, $contactMethod->getKey());
            }

            $contactMethod = ContactMethod::query()->whereKey($contactMethod->getKey())->lockForUpdate()->firstOrFail();
            $contactMethod->is_primary = true;
            if (! app(SynchronizePrimaryContactMethodAction::class)->execute($contactMethod)) {
                throw new RuntimeException('The contact write was cancelled.');
            }

            return $contactMethod->refresh();
        }, attempts: 3);
    }
}
