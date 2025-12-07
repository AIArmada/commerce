<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryTransferred;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Transfer inventory between locations.
 */
final class TransferInventory
{
    use AsAction;

    public function __construct(
        private readonly CheckLowInventory $checkLowInventory,
    ) {}

    /**
     * Transfer inventory between locations.
     *
     * @return array{from: InventoryMovement, to: InventoryMovement}
     *
     * @throws InsufficientInventoryException
     */
    public function handle(
        Model $model,
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        ?string $note = null,
        ?string $userId = null
    ): array {
        return DB::transaction(function () use ($model, $fromLocationId, $toLocationId, $quantity, $note, $userId): array {
            // Lock source location
            $fromLevel = InventoryLevel::where('inventoriable_type', $model->getMorphClass())
                ->where('inventoriable_id', $model->getKey())
                ->where('location_id', $fromLocationId)
                ->lockForUpdate()
                ->first();

            $available = $fromLevel?->quantity_available ?? 0;

            if (! $fromLevel || $available < $quantity) {
                throw new InsufficientInventoryException(
                    "Insufficient inventory at source location {$fromLocationId}. Available: {$available}, requested: {$quantity}",
                    $model->getKey(),
                    $quantity,
                    $available
                );
            }

            // Get or create destination level
            $toLevel = $this->getOrCreateLevel($model, $toLocationId);

            // Update source
            $fromPrevious = $fromLevel->quantity_on_hand;
            $fromLevel->quantity_on_hand -= $quantity;
            $fromLevel->save();

            // Update destination
            $toPrevious = $toLevel->quantity_on_hand;
            $toLevel->quantity_on_hand += $quantity;
            $toLevel->save();

            // Create movements
            $fromMovement = InventoryMovement::create([
                'inventoriable_type' => $model->getMorphClass(),
                'inventoriable_id' => $model->getKey(),
                'location_id' => $fromLocationId,
                'movement_type' => MovementType::Transfer,
                'quantity' => -$quantity,
                'previous_quantity' => $fromPrevious,
                'new_quantity' => $fromLevel->quantity_on_hand,
                'note' => $note,
                'user_id' => $userId,
                'related_location_id' => $toLocationId,
            ]);

            $toMovement = InventoryMovement::create([
                'inventoriable_type' => $model->getMorphClass(),
                'inventoriable_id' => $model->getKey(),
                'location_id' => $toLocationId,
                'movement_type' => MovementType::Transfer,
                'quantity' => $quantity,
                'previous_quantity' => $toPrevious,
                'new_quantity' => $toLevel->quantity_on_hand,
                'note' => $note,
                'user_id' => $userId,
                'related_location_id' => $fromLocationId,
                'related_movement_id' => $fromMovement->id,
            ]);

            Event::dispatch(new InventoryTransferred($model, $fromLevel, $toLevel, $fromMovement));

            $this->checkLowInventory->handle($model, $fromLevel);
            $this->checkLowInventory->handle($model, $toLevel);

            return ['from' => $fromMovement, 'to' => $toMovement];
        });
    }

    private function getOrCreateLevel(Model $model, string $locationId): InventoryLevel
    {
        return InventoryLevel::firstOrCreate(
            [
                'inventoriable_type' => $model->getMorphClass(),
                'inventoriable_id' => $model->getKey(),
                'location_id' => $locationId,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_available' => 0,
                'reorder_point' => config('inventory.default_reorder_point', 10),
                'reorder_quantity' => config('inventory.default_reorder_quantity', 50),
            ]
        );
    }
}
