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
use InvalidArgumentException;
use RuntimeException;

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

        $level = InventoryOwnerScope::forMutation(InventoryLevel::query())->firstOrCreate($identity);

        if (! $level->exists) {
            throw new RuntimeException('Inventory level creation was cancelled.');
        }

        return $level;
    }

    public function guardDirectOnHandChange(InventoryLevel $level): void
    {
        $current = InventoryOwnerScope::forMutation(InventoryLevel::query())
            ->whereKey($level->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($current->quantity_on_hand !== (int) $level->getOriginal('quantity_on_hand')) {
            throw new RuntimeException('Inventory level has changed; reload it before adjusting stock.');
        }

        if ($level->quantity_on_hand < $current->quantity_reserved) {
            throw new InvalidArgumentException('On-hand quantity cannot be less than reserved stock.');
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

        if (! $level->wasChanged('quantity_on_hand')) {
            return;
        }

        $old = (int) $level->getOriginal('quantity_on_hand');
        $new = (int) $level->quantity_on_hand;

        if ($old === $new) {
            return;
        }

        $difference = $new - $old;

        $movement = InventoryMovement::create([
            'inventoryable_type' => $level->inventoryable_type,
            'inventoryable_id' => $level->inventoryable_id,
            'from_location_id' => $difference < 0 ? $level->location_id : null,
            'to_location_id' => $difference > 0 ? $level->location_id : null,
            'quantity' => abs($difference),
            'quantity_before' => $old,
            'quantity_after' => $new,
            'type' => MovementType::Adjustment->value,
            'reason' => 'direct-level-update',
            'note' => sprintf('Direct level update from %d to %d', $old, $new),
            'occurred_at' => CarbonImmutable::now(),
        ]);

        if (! $movement->exists) {
            throw new RuntimeException('Inventory movement creation was cancelled.');
        }
    }
}
