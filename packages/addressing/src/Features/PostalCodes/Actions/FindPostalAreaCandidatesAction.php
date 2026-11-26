<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\PostalCodes\Actions;

use AIArmada\Addressing\Features\PostalCodes\Api\PostalAreaLookup;
use AIArmada\Addressing\Features\PostalCodes\Models\PostalCode;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Support\ModelResolver;

final readonly class FindPostalAreaCandidatesAction implements PostalAreaLookup
{
    public function candidateAreaIds(string $countryCode, string $code, ?string $countryId = null): array
    {
        $areas = ModelResolver::postalCodeClass()::query()
            ->where('country_code', $countryCode)
            ->where('code', $code)
            ->where('is_active', true)
            ->with('areas')
            ->get()
            ->flatMap(static fn (PostalCode $postalCode) => $postalCode->areas)
            ->filter(static fn (AddressArea $area): bool => $area->is_active !== false)
            ->filter(static fn (AddressArea $area): bool => $countryId === null
                || (string) $area->country_id === $countryId)
            ->unique(static fn (AddressArea $area): string => (string) $area->getKey())
            ->values();

        $primary = $areas->filter(static function (AddressArea $area): bool {
            $pivot = $area->getRelation('pivot');

            return $pivot !== null && (bool) $pivot->is_primary;
        });

        return ($primary->isNotEmpty() ? $primary : $areas)
            ->map(static fn (AddressArea $area): string => (string) $area->getKey())
            ->values()
            ->all();
    }
}
