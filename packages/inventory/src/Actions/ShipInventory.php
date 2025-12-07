<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryShipped;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Ship inventory from a location.
 */
final class ShipInventory
{
    use AsAction;

    public function __construct(
        private readonly CheckLowInventory $checkLowInventory,
    ) {}

    /**
     * Ship inventory from a location.
     *
     * @throws InsufficientInventoryException
     */
    public function handle(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($model, $locationId, $quantity, $reason, $reference, $note, $userId): InventoryMovement {
            $level = InventoryLevel::where('inventoriable_type', $model->getMorphClass())
                ->where('inventoriable_id', $model->getKey())
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            $available = $level?->quantity_available ?? 0;

            if (! $level || $available < $quantity) {
                throw new InsufficientInventoryException(
                    "Insufficient inventory at location {$locationId}. Available: {$available}, requested: {$quantity}",
                    $model->getKey(),
                    $quantity,
                    $available
                );
            }

            $previousQuantity = $level->quantity_on_hand;
            $level->quantity_on_hand -= $quantity;
            $level->save();

            $movement = InventoryMovement::create([
                'inventoriable_type' => $model->getMorphClass(),
                'inventoriable_id' => $model->getKey(),
                'location_id' => $locationId,
                'movement_type' => MovementType::Shipment,
                'quantity' => -$quantity,
                'previous_quantity' => $previousQuantity,
                'new_quantity' => $level->quantity_on_hand,
                'reason' => $reason,
                'reference' => $reference,
                'note' => $note,
                'user_id' => $userId,
            ]);

            Event::dispatch(new InventoryShipped($model, $level, $movement));

            $this->checkLowInventory->handle($model, $level);

            return $movement;
        });
    }
}
