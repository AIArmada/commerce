<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Queries;

use AIArmada\Inventory\Features\Traceability\Api\BatchIdentity;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Support\InventoryOwnerScope;

final readonly class BatchIdentityQuery implements BatchIdentity
{
    public function matchesWritableBatch(string $batchId, string $type, string $id, ?string $locationId): bool
    {
        $query = InventoryOwnerScope::forMutation(InventoryBatch::query())
            ->whereKey($batchId)
            ->where('inventoryable_type', $type)
            ->where('inventoryable_id', $id);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return $query->exists();
    }
}
