<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Features\Stock\Events\InventoryShipped;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class ShipInventory
{
    use AsAction;

    public function __construct(
        private readonly CheckLowInventory $thresholds,
    ) {}

    public function handle(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        return DB::transaction(function () use ($model, $locationId, $quantity, $reason, $reference, $note, $userId, $occurredAt): InventoryMovement {
            $levelQuery = InventoryOwnerScope::forMutation(
                InventoryLevel::query()
                    ->where('inventoryable_type', $model->getMorphClass())
                    ->where('inventoryable_id', $model->getKey())
                    ->where('location_id', $locationId)
            );

            $level = $levelQuery->lockForUpdate()->first();

            $available = $level?->available ?? 0;

            if ($level === null || $available < $quantity) {
                throw InsufficientInventoryException::forLocation($locationId, $quantity, $available);
            }

            $before = $level->quantity_on_hand;
            $level->suppressLedgerAudit = true;

            try {
                if (! $level->update(['quantity_on_hand' => $before - $quantity])) {
                    throw new RuntimeException('Inventory balance update was cancelled.');
                }
            } finally {
                $level->suppressLedgerAudit = false;
            }

            $movement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'from_location_id' => $locationId,
                'quantity' => $quantity,
                'quantity_before' => $before,
                'quantity_after' => $level->quantity_on_hand,
                'type' => MovementType::Shipment->value,
                'reason' => $reason,
                'reference' => $reference,
                'user_id' => $userId,
                'note' => $note,
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
            ]);

            if (! $movement->exists) {
                throw new RuntimeException('Inventory movement creation was cancelled.');
            }

            Event::dispatch(new InventoryShipped($model, $level, $movement));

            $this->thresholds->handle($model, $level);

            return $movement;
        }, 3);
    }
}
