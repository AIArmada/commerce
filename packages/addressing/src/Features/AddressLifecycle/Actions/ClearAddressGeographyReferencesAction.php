<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\AddressLifecycle\Actions;

use AIArmada\Addressing\Contracts\AddressAssignmentWriter;
use AIArmada\Addressing\Features\AddressLifecycle\Api\AddressReferenceMaintenance;
use AIArmada\Addressing\Support\AddressAreaAssignmentOwnerScope;
use AIArmada\Addressing\Support\ModelResolver;

final readonly class ClearAddressGeographyReferencesAction implements AddressReferenceMaintenance
{
    private const int CHUNK_SIZE = 500;

    public function clearState(string $stateId): void
    {
        $addressClass = ModelResolver::addressClass();

        (new $addressClass)->getConnection()->transaction(function () use ($stateId, $addressClass): void {
            $lastId = null;

            do {
                $ids = $addressClass::query()->withoutOwnerScope()
                    ->where('state_id', $stateId)
                    ->when($lastId !== null, static fn ($query) => $query->where('id', '>', $lastId))
                    ->orderBy('id')
                    ->limit(self::CHUNK_SIZE)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->all();

                if ($ids === []) {
                    break;
                }

                $addressClass::query()->withoutOwnerScope()
                    ->whereIn('id', $ids)
                    ->where('state_id', $stateId)
                    ->update(['state_id' => null]);

                foreach ($ids as $id) {
                    $this->pruneAddressAssignments($addressClass, $id);
                }

                $lastId = end($ids);
            } while (true);
        });
    }

    public function clearCity(string $cityId): void
    {
        ModelResolver::addressClass()::query()->withoutOwnerScope()
            ->where('city_id', $cityId)
            ->update(['city_id' => null]);
    }

    public function clearArea(string $areaId): void
    {
        ModelResolver::areaAssignmentClass()::query()
            ->withoutGlobalScope(AddressAreaAssignmentOwnerScope::class)
            ->where('address_area_id', $areaId)
            ->delete();
    }

    private function pruneAddressAssignments(string $addressClass, string $addressId): void
    {
        $address = $addressClass::query()->withoutOwnerScope()->whereKey($addressId)->first();

        if ($address === null) {
            return;
        }

        $current = ModelResolver::areaAssignmentClass()::query()
            ->withoutGlobalScope(AddressAreaAssignmentOwnerScope::class)
            ->where('address_id', $addressId)
            ->pluck('address_area_id', 'role')
            ->map(static fn (mixed $areaId): string => (string) $areaId)
            ->all();

        if ($current === []) {
            return;
        }

        $removedRoles = array_map(
            static fn (int | string $role): string => (string) $role,
            array_keys(array_diff_key($current, app(AddressAssignmentWriter::class)->narrowToValidAssignments($address->country_code, $address->state_id, $current))),
        );

        if ($removedRoles === []) {
            return;
        }

        ModelResolver::areaAssignmentClass()::query()
            ->withoutGlobalScope(AddressAreaAssignmentOwnerScope::class)
            ->where('address_id', $addressId)
            ->whereIn('role', $removedRoles)
            ->delete();
    }
}
