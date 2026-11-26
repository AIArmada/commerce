<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\Geography\Actions;

use AIArmada\Addressing\Contracts\AddressAreaAliasDeleter;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Support\ModelResolver;
use DomainException;
use Illuminate\Validation\ValidationException;

final readonly class DeleteAddressAreaAliasAction implements AddressAreaAliasDeleter
{
    public function execute(AddressArea $area, AddressAreaName $alias): void
    {
        $area->getConnection()->transaction(function () use ($area, $alias): void {
            $areaClass = ModelResolver::areaClass();
            $persistedArea = $areaClass::query()->lockForUpdate()->findOrFail($area->getKey());
            $aliasClass = ModelResolver::areaNameClass();
            $persistedAlias = $aliasClass::query()->lockForUpdate()->findOrFail($alias->getKey());

            if ($persistedAlias->source !== 'manual') {
                throw ValidationException::withMessages([
                    'alias' => __('Provider-owned aliases can only be removed by their geography provider.'),
                ]);
            }

            if ((string) $persistedAlias->address_area_id !== (string) $persistedArea->getKey()) {
                throw ValidationException::withMessages([
                    'alias' => __('The alias does not belong to the selected area.'),
                ]);
            }

            if (! $persistedAlias->delete()) {
                throw new DomainException('The address area alias could not be deleted.');
            }
        });
    }
}
