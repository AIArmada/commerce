<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Persistence;

use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

final readonly class InventoryLevelStore
{
    public function getOrCreate(Model $model, string $locationId): InventoryLevel
    {
        if (! InventoryOwnerScope::forMutation(InventoryLocation::query())->whereKey($locationId)->exists()) {
            throw new InvalidArgumentException('Invalid location for current owner');
        }

        $identity = [
            'inventoryable_type' => $model->getMorphClass(),
            'inventoryable_id' => $model->getKey(),
            'location_id' => $locationId,
        ];

        try {
            return InventoryOwnerScope::forMutation(InventoryLevel::query())->firstOrCreate(
                $identity,
                [
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ]
            );
        } catch (QueryException $exception) {
            // The framework already rescues plain unique races, but its
            // recovery lookup is over-constrained by the mutable quantity
            // defaults. Re-select by identity so a loser never 500s when
            // the winner's row has since moved.
            if (! in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true)) {
                throw $exception;
            }

            $level = InventoryOwnerScope::forMutation(InventoryLevel::query())->where($identity)->first();

            if (! $level instanceof InventoryLevel) {
                throw $exception;
            }

            return $level;
        }
    }
}
