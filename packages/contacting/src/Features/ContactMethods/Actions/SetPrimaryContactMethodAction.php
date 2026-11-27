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
        $contactMethod = ContactMethod::ownerScopeConfig()->enabled
            ? OwnerWriteGuard::findOrFailForOwner(ContactMethod::class, $contactMethod->getKey())
            : ContactMethod::query()->whereKey($contactMethod->getKey())->firstOrFail();

        $contactMethod->getConnection()->transaction(function () use ($contactMethod): void {
            $contactMethod->is_primary = true;
            if (! app(SynchronizePrimaryContactMethodAction::class)->execute($contactMethod)) {
                throw new RuntimeException('The contact write was cancelled.');
            }
        });

        return $contactMethod->refresh();
    }
}
