<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\AddressLifecycle\Actions;

use AIArmada\Addressing\Features\AddressLifecycle\Api\AddressDeletionCleanup;
use AIArmada\Addressing\Support\AddressOwnerGuard;
use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\CommerceSupport\Support\OwnerScope;
use RuntimeException;

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
                if (! $assignment->delete()) {
                    throw new RuntimeException('An address area assignment could not be removed.');
                }
            }

            foreach ($address->addressableLinks()->get() as $link) {
                if (! $link->delete()) {
                    throw new RuntimeException('An address attachment could not be removed.');
                }
            }

            $address->snapshots()->update(['address_id' => null]);
        });
    }
}
