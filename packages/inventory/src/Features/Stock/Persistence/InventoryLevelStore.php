<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Persistence;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
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

    /**
     * Record an adjustment movement for direct on-hand writes.
     *
     * Ledger services (receive/ship/transfer/adjust/allocate) record their
     * own movements and set $suppressLedgerAudit; any other on-hand change
     * (operator edits, one-off scripts) is auto-audited here so the
     * movement ledger can never silently disagree with the level.
     */
    public function auditDirectOnHandChange(InventoryLevel $level): void
    {
        if (! $level->exists || $level->suppressLedgerAudit) {
            return;
        }

        if (! $level->isDirty('quantity_on_hand')) {
            return;
        }

        $old = (int) $level->getOriginal('quantity_on_hand');
        $new = (int) $level->quantity_on_hand;

        if ($old === $new) {
            return;
        }

        $difference = $new - $old;

        InventoryMovement::create([
            'inventoryable_type' => $level->inventoryable_type,
            'inventoryable_id' => $level->inventoryable_id,
            'from_location_id' => $difference < 0 ? $level->location_id : null,
            'to_location_id' => $difference > 0 ? $level->location_id : null,
            'quantity' => abs($difference),
            'type' => MovementType::Adjustment->value,
            'reason' => 'direct-level-update',
            'note' => sprintf('Direct level update from %d to %d', $old, $new),
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }
}
