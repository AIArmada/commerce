<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\MovementReferences;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;

final readonly class MovementReferencesQuery implements MovementReferences
{
    public function matching(array $references): array
    {
        $matches = [];

        foreach (array_chunk(array_values(array_unique($references)), 200) as $chunk) {
            $rows = InventoryOwnerScope::applyToLocationQuery(InventoryMovement::query())
                ->whereIn('reference', $chunk)
                ->select(['reference', 'owner_type', 'owner_id'])
                ->distinct()
                ->toBase()
                ->get();

            foreach ($rows as $row) {
                $matches[] = [
                    'reference' => (string) $row->reference,
                    'owner_type' => $row->owner_type === null ? null : (string) $row->owner_type,
                    'owner_id' => $row->owner_id === null ? null : (string) $row->owner_id,
                ];
            }
        }

        return $matches;
    }
}
