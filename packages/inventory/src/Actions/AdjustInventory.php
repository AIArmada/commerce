<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAdjusted;
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
            $level = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
                ->whereKey($level->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($newQuantity < $level->quantity_reserved) {
                throw new InvalidArgumentException('On-hand quantity cannot be less than reserved stock.');
            }

            $oldQuantity = $level->quantity_on_hand;
            $difference = $newQuantity - $oldQuantity;

            if ($difference === 0) {
                throw new InvalidArgumentException('No adjustment needed, quantities are equal');
            }

            // Ledger path: the movement below is the audit record, so the
            // level's direct-write auto-audit must stand down.
            $level->suppressLedgerAudit = true;
            $level->update(['quantity_on_hand' => $newQuantity]);

            $movement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'from_location_id' => $difference < 0 ? $locationId : null,
                'to_location_id' => $difference > 0 ? $locationId : null,
                'quantity' => abs($difference),
                'type' => MovementType::Adjustment->value,
                'reason' => $reason,
                'user_id' => $userId,
                'note' => $note ?? sprintf('Adjusted from %d to %d', $oldQuantity, $newQuantity),
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
            ]);

            Event::dispatch(new InventoryAdjusted($model, $level, $movement, $oldQuantity, $newQuantity));

            $this->thresholds->handle($model, $level);

            return $movement;
        });
    }
}
