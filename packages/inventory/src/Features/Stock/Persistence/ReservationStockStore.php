<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Persistence;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Stock\Actions\CheckLowInventory;
use AIArmada\Inventory\Features\Stock\Api\CommittedStockMovement;
use AIArmada\Inventory\Features\Stock\Api\ReservationStock;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ReservationStockStore implements ReservationStock
{
    public function reserve(string $levelId, int $quantity): void
    {
        DB::transaction(function () use ($levelId, $quantity): void {
            $record = $this->lock($levelId);

            if ($quantity <= 0 || $record->available < $quantity) {
                throw new InvalidArgumentException('Reservation quantity exceeds available stock.');
            }

            $record->increment('quantity_reserved', $quantity);
        });
    }

    public function release(string $levelId, int $quantity): void
    {
        DB::transaction(function () use ($levelId, $quantity): void {
            $record = $this->lock($levelId);

            if ($quantity <= 0 || $record->quantity_reserved < $quantity) {
                throw new InvalidArgumentException('Release quantity exceeds reserved stock.');
            }

            $record->decrement('quantity_reserved', $quantity);
        });
    }

    public function commit(string $levelId, int $quantity, ?string $orderId, string $note, ?string $batchId = null): CommittedStockMovement
    {
        return DB::transaction(function () use ($levelId, $quantity, $orderId, $note, $batchId): CommittedStockMovement {
            $record = $this->lock($levelId);

            if ($quantity <= 0 || $record->quantity_reserved < $quantity || $record->quantity_on_hand < $quantity) {
                throw new InvalidArgumentException('Commit quantity exceeds reserved or on-hand stock.');
            }

            $before = $record->quantity_on_hand;
            $record->suppressLedgerAudit = true;

            try {
                $record->forceFill([
                    'quantity_on_hand' => $before - $quantity,
                    'quantity_reserved' => $record->quantity_reserved - $quantity,
                ])->save();
            } finally {
                $record->suppressLedgerAudit = false;
            }

            $movement = InventoryMovement::create([
                'inventoryable_type' => $record->inventoryable_type,
                'inventoryable_id' => $record->inventoryable_id,
                'from_location_id' => $record->location_id,
                'batch_id' => $batchId,
                'quantity' => $quantity,
                'quantity_before' => $before,
                'quantity_after' => $record->quantity_on_hand,
                'type' => MovementType::Shipment->value,
                'reason' => 'sale',
                'reference' => $orderId,
                'note' => $note,
                'occurred_at' => CarbonImmutable::now(),
            ]);

            if ($record->inventoryable instanceof Model) {
                app(CheckLowInventory::class)->handle($record->inventoryable, $record);
            }

            return new CommittedStockMovement(
                id: $movement->id,
                inventoryableType: $record->inventoryable_type,
                inventoryableId: $record->inventoryable_id,
                locationId: $record->location_id,
                quantity: $quantity,
                type: $movement->type,
                reference: $orderId,
                batchId: $batchId,
            );
        });
    }

    private function lock(string $levelId): InventoryLevel
    {
        return InventoryOwnerScope::forMutation(InventoryLevel::query())
            ->whereKey($levelId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
