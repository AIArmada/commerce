<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryTransferred;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Stock\Persistence\InventoryLevelStore;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
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
            if (InventoryOwnerScope::isEnabled()
                && InventoryOwnerScope::forMutation(InventoryLocation::query())->whereIn('id', [$fromLocationId, $toLocationId])->count() !== 2) {
                throw new AuthorizationException('Cross-owner inventory movements are not allowed.');
            }

            // Create the destination before taking both stock locks, then
            // acquire the rows in the same order for either transfer direction.
            $this->levels->getOrCreate($model, $toLocationId);

            $levels = InventoryOwnerScope::forMutation(InventoryLevel::query())
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereIn('location_id', [$fromLocationId, $toLocationId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('location_id');

            $fromLevel = $levels->get($fromLocationId);
            $toLevel = $levels->get($toLocationId);
            $available = $fromLevel?->available ?? 0;

            if (! $fromLevel instanceof InventoryLevel || $available < $quantity) {
                throw InsufficientInventoryException::forLocation($fromLocationId, $quantity, $available);
            }

            if (! $toLevel instanceof InventoryLevel) {
                throw new InvalidArgumentException('Destination inventory level is not accessible.');
            }

            if (InventoryOwnerScope::isEnabled()
                && ($fromLevel->owner_type !== $toLevel->owner_type || (string) $fromLevel->owner_id !== (string) $toLevel->owner_id)) {
                throw new AuthorizationException('Cross-owner inventory movements are not allowed.');
            }

            $fromLevel->suppressLedgerAudit = true;
            $toLevel->suppressLedgerAudit = true;

            try {
                $fromLevel->update(['quantity_on_hand' => $fromLevel->quantity_on_hand - $quantity]);
                $toLevel->update(['quantity_on_hand' => $toLevel->quantity_on_hand + $quantity]);
            } finally {
                $fromLevel->suppressLedgerAudit = false;
                $toLevel->suppressLedgerAudit = false;
            }

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
