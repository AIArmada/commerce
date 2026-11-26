<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\Geography\Actions;

use AIArmada\Addressing\Contracts\AddressAreaDeletionGuard;

use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\Addressing\Models\AddressArea;
use Illuminate\Validation\ValidationException;

final readonly class GuardAddressAreaDeletionAction implements AddressAreaDeletionGuard
{
    public function handle(AddressArea $addressArea): void
    {
        $key = (string) $addressArea->getKey();

        if (ModelResolver::areaRelationshipClass()::query()
            ->where('parent_address_area_id', $key)
            ->where('relationship_type', 'contains')
            ->exists()) {
            throw ValidationException::withMessages([
                'address_area' => 'Delete or reassign this address area\'s child areas before deleting it.',
            ]);
        }

        if (ModelResolver::addressClass()::query()->withoutOwnerScope()->whereHas(
            'areaAssignments',
            static fn ($query) => $query->withoutGlobalScope(\AIArmada\Addressing\Support\AddressAreaAssignmentOwnerScope::class)->where('address_area_id', $key),
        )->exists()) {
            throw ValidationException::withMessages([
                'address_area' => 'This address area is still referenced by one or more addresses.',
            ]);
        }
    }
}
