<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryReceived;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Stock\Persistence\InventoryLevelStore;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class ReceiveInventory
{
    use AsAction;

    public function __construct(
        private readonly InventoryLevelStore $levels,
    ) {}

    public function handle(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        return DB::transaction(function () use ($model, $locationId, $quantity, $reason, $note, $userId, $occurredAt): InventoryMovement {
            $level = $this->levels->getOrCreate($model, $locationId);
            $level->incrementOnHand($quantity);

            $movement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'to_location_id' => $locationId,
                'quantity' => $quantity,
                'type' => MovementType::Receipt->value,
                'reason' => $reason,
                'user_id' => $userId,
                'note' => $note,
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
            ]);

            Event::dispatch(new InventoryReceived($model, $level, $movement));

            return $movement;
        });
    }
}
