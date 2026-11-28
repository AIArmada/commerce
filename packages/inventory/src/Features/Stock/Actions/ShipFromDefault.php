<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Actions;

use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ShipFromDefault
{
    public function __construct(
        private SaveInventoryLocation $locations,
        private ShipInventory $stock,
    ) {}

    public function handle(
        Model $model,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        return DB::transaction(function () use ($model, $quantity, $reason, $reference, $note, $userId): InventoryMovement {
            $location = $this->locations->getOrCreateDefault();

            return $this->stock->handle($model, $location->id, $quantity, $reason, $reference, $note, $userId);
        }, 3);
    }
}
