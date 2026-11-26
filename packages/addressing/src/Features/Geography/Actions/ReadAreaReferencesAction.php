<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\Geography\Actions;

use AIArmada\Addressing\Features\Geography\Api\AreaReferenceReader;
use AIArmada\Addressing\Features\Geography\Models\AddressAreaName;
use AIArmada\Addressing\Features\Geography\Models\AddressAreaRelationship;
use AIArmada\Addressing\Support\ModelResolver;
use Carbon\CarbonImmutable;

final readonly class ReadAreaReferencesAction implements AreaReferenceReader
{
    public function aliases(array $areaIds, ?string $source = null): array
    {
        if ($areaIds === []) {
            return [];
        }

        return ModelResolver::areaNameClass()::query()
            ->whereIn('address_area_id', $areaIds)
            ->when($source !== null, static fn ($query) => $query->where('source', $source))
            ->get(['address_area_id', 'name', 'name_type', 'is_preferred'])
            ->map(static fn (AddressAreaName $alias): array => [
                'area_id' => (string) $alias->getAttribute('address_area_id'),
                'name' => (string) $alias->getAttribute('name'),
                'name_type' => (string) $alias->getAttribute('name_type'),
                'is_preferred' => (bool) $alias->getAttribute('is_preferred'),
            ])
            ->values()
            ->all();
    }

    public function activeContainmentParents(array $childIds): array
    {
        if ($childIds === []) {
            return [];
        }

        $today = CarbonImmutable::now();

        return ModelResolver::areaRelationshipClass()::query()
            ->whereIn('child_address_area_id', $childIds)
            ->where('relationship_type', 'contains')
            ->whereHas('parent', static fn ($query) => $query->where('is_active', true))
            ->where(static fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(static fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today))
            ->get(['parent_address_area_id', 'child_address_area_id', 'hierarchy_type'])
            ->map(static fn (AddressAreaRelationship $edge): array => [
                'parent_id' => (string) $edge->getAttribute('parent_address_area_id'),
                'child_id' => (string) $edge->getAttribute('child_address_area_id'),
                'hierarchy_type' => $edge->getAttribute('hierarchy_type') === null ? null : (string) $edge->getAttribute('hierarchy_type'),
            ])
            ->values()
            ->all();
    }
}
