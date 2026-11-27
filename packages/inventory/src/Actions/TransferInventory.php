<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryTransferred;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Persistence\InventoryLevelStore;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class TransferInventory
{
    use AsAction;

    public function __construct(
        private readonly InventoryLevelStore $levels,
        private readonly CheckLowInventory $thresholds,
    ) {}

    public function handle(
        Model $model,
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        if ($fromLocationId === $toLocationId) {
            throw new InvalidArgumentException('Source and destination locations must be different');
        }

        return DB::transaction(function () use ($model, $fromLocationId, $toLocationId, $quantity, $note, $userId, $occurredAt): InventoryMovement {
            $fromLevelQuery = InventoryOwnerScope::applyToLocationQuery(
                InventoryLevel::query()
                    ->where('inventoryable_type', $model->getMorphClass())
                    ->where('inventoryable_id', $model->getKey())
                    ->where('location_id', $fromLocationId)
            );

            $fromLevel = $fromLevelQuery->lockForUpdate()->first();

            $available = $fromLevel?->available ?? 0;

            if ($fromLevel === null || $available < $quantity) {
                throw InsufficientInventoryException::forLocation($fromLocationId, $quantity, $available);
            }

            $toLevel = $this->levels->getOrCreate($model, $toLocationId);

            $fromLevel->decrementOnHand($quantity);
            $toLevel->incrementOnHand($quantity);

            $movement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'quantity' => $quantity,
                'type' => MovementType::Transfer->value,
                'user_id' => $userId,
                'note' => $note,
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
            ]);

            Event::dispatch(new InventoryTransferred($model, $fromLevel, $toLevel, $movement));

            $this->thresholds->handle($model, $fromLevel);

            return $movement;
        });
    }
}
