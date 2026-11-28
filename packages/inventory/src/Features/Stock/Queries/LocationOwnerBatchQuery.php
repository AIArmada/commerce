<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Inventory\Features\Stock\Api\LocationOwnerBatch;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;

final readonly class LocationOwnerBatchQuery implements LocationOwnerBatch
{
    public function forEach(callable $callback): array
    {
        $runner = new OwnerBatchRunner(
            InventoryLocation::class,
            ['enabled' => 'inventory.owner.enabled'],
        );

        return array_values($runner->forEach($callback)->all());
    }
}
