<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Stock\Events\InventoryAdjusted;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Stock\Persistence\InventoryLevelStore;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class AdjustInventory
{
    use AsAction;

    public function __construct(
        private readonly InventoryLevelStore $levels,
        private readonly CheckLowInventory $thresholds,
    ) {}

    public function handle(
        Model $model,
        string $locationId,
        int $newQuantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        if ($newQuantity < 0) {
            throw new InvalidArgumentException('New quantity cannot be negative');
        }

        return DB::transaction(function () use ($model, $locationId, $newQuantity, $reason, $note, $userId, $occurredAt): InventoryMovement {
            $level = $this->levels->getOrCreate($model, $locationId);
            $level = InventoryOwnerScope::forMutation(InventoryLevel::query())
                ->whereKey($level->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($newQuantity < $level->quantity_reserved) {
                throw new InvalidArgumentException('On-hand quantity cannot be less than reserved stock.');
            }

            $oldQuantity = $level->quantity_on_hand;
            $difference = $newQuantity - $oldQuantity;

            if ($difference === 0 && $reason !== 'cycle_count') {
                throw new InvalidArgumentException('No adjustment needed, quantities are equal');
            }

            // Ledger path: the movement below is the audit record, so the
            // level's direct-write auto-audit must stand down.
            if ($difference !== 0) {
                $level->suppressLedgerAudit = true;

                try {
                    if (! $level->update(['quantity_on_hand' => $newQuantity])) {
                        throw new RuntimeException('Inventory balance update was cancelled.');
                    }
                } finally {
                    $level->suppressLedgerAudit = false;
                }
            }

            $movement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'from_location_id' => $difference < 0 ? $locationId : null,
                'to_location_id' => $difference >= 0 ? $locationId : null,
                'quantity' => abs($difference),
                'quantity_before' => $oldQuantity,
                'quantity_after' => $newQuantity,
                'type' => MovementType::Adjustment->value,
                'reason' => $reason,
                'user_id' => $userId,
                'note' => $note ?? sprintf('Adjusted from %d to %d', $oldQuantity, $newQuantity),
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
            ]);

            if (! $movement->exists) {
                throw new RuntimeException('Inventory movement creation was cancelled.');
            }

            if ($difference !== 0) {
                Event::dispatch(new InventoryAdjusted($model, $level, $movement, $oldQuantity, $newQuantity));

                $this->thresholds->handle($model, $level);
            }

            return $movement;
        });
    }
}
