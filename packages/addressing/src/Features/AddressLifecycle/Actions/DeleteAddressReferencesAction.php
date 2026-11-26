<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\AddressLifecycle\Actions;

use AIArmada\Addressing\Features\AddressLifecycle\Api\AddressDeletionCleanup;
use AIArmada\Addressing\Support\AddressOwnerGuard;
use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\CommerceSupport\Support\OwnerScope;

final readonly class DeleteAddressReferencesAction implements AddressDeletionCleanup
{
    public function execute(string $addressId): void
    {
        $addressClass = ModelResolver::addressClass();

        (new $addressClass)->getConnection()->transaction(function () use ($addressClass, $addressId): void {
            AddressOwnerGuard::assertAddressIsWritable($addressId);
            $query = $addressClass::query();

            if (! $addressClass::ownerScopeConfig()->enabled) {
                $query->withoutGlobalScope(OwnerScope::class);
            }

            $address = $query->whereKey($addressId)->lockForUpdate()->firstOrFail();

            foreach ($address->areaAssignments()->get() as $assignment) {
                $assignment->delete();
            }

            foreach ($address->addressableLinks()->get() as $link) {
                $link->delete();
            }

            $address->snapshots()->update(['address_id' => null]);
        });
    }
}
