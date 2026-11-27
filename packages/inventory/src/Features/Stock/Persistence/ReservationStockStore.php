<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Persistence;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Stock\Actions\CheckLowInventory;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ReservationStockStore
{
    public function reserve(InventoryLevel $level, int $quantity): void
    {
        DB::transaction(function () use ($level, $quantity): void {
            $record = $this->lock($level);

            if ($quantity <= 0 || $record->available < $quantity) {
                throw new InvalidArgumentException('Reservation quantity exceeds available stock.');
            }

            $record->incrementReserved($quantity);
        });
    }

    public function release(InventoryLevel $level, int $quantity): void
    {
        DB::transaction(function () use ($level, $quantity): void {
            $record = $this->lock($level);

            if ($quantity <= 0 || $record->quantity_reserved < $quantity) {
                throw new InvalidArgumentException('Release quantity exceeds reserved stock.');
            }

            $record->decrementReserved($quantity);
        });
    }

    public function commit(InventoryLevel $level, int $quantity, ?string $orderId, string $note): InventoryMovement
    {
        return DB::transaction(function () use ($level, $quantity, $orderId, $note): InventoryMovement {
            $record = $this->lock($level);

            if ($quantity <= 0 || $record->quantity_reserved < $quantity || $record->quantity_on_hand < $quantity) {
                throw new InvalidArgumentException('Commit quantity exceeds reserved or on-hand stock.');
            }

            $record->decrementOnHand($quantity);
            $record->decrementReserved($quantity);

            $movement = InventoryMovement::create([
                'inventoryable_type' => $record->inventoryable_type,
                'inventoryable_id' => $record->inventoryable_id,
                'from_location_id' => $record->location_id,
                'quantity' => $quantity,
                'type' => MovementType::Shipment->value,
                'reason' => 'sale',
                'reference' => $orderId,
                'note' => $note,
                'occurred_at' => CarbonImmutable::now(),
            ]);

            if ($record->inventoryable instanceof Model) {
                app(CheckLowInventory::class)->handle($record->inventoryable, $record);
            }

            return $movement;
        });
    }

    private function lock(InventoryLevel $level): InventoryLevel
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->whereKey($level->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
