<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Actions;

use AIArmada\Inventory\Contracts\BatchReservationBalances;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryReceived;
use AIArmada\Inventory\Features\Stock\Api\RestoreOrderStock as RestoreOrderStockContract;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Stock\Persistence\InventoryLevelStore;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final readonly class RestoreOrderStock implements RestoreOrderStockContract
{
    public function __construct(
        private InventoryLevelStore $levels,
        private BatchReservationBalances $batches,
    ) {}

    public function restore(string $orderId, string $orderNumber): int
    {
        return DB::transaction(function () use ($orderId, $orderNumber): int {
            $movements = InventoryOwnerScope::forMutation(InventoryMovement::query())
                ->where('type', MovementType::Shipment->value)
                ->where('reference', $orderId)
                ->whereIn('reason', ['order', 'sale'])
                ->with('inventoryable')
                ->orderBy('from_location_id')
                ->orderBy('inventoryable_type')
                ->orderBy('inventoryable_id')
                ->orderBy('id')
                ->get();

            foreach ($movements as $movement) {
                if ($movement->from_location_id === null || ! $movement->inventoryable instanceof Model || $movement->quantity <= 0) {
                    throw new RuntimeException('Cannot restore an order movement without its original stock identity.');
                }

                if ($movement->batch_id !== null) {
                    $this->batches->restore(
                        $movement->batch_id,
                        $movement->inventoryable_type,
                        $movement->inventoryable_id,
                        $movement->from_location_id,
                        $movement->quantity,
                    );
                }

                $level = $this->levels->getOrCreate($movement->inventoryable, $movement->from_location_id);
                $level = InventoryOwnerScope::forMutation(InventoryLevel::query())
                    ->whereKey($level->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $before = $level->quantity_on_hand;
                $level->suppressLedgerAudit = true;

                try {
                    if (! $level->update(['quantity_on_hand' => $before + $movement->quantity])) {
                        throw new RuntimeException('Inventory balance update was cancelled.');
                    }
                } finally {
                    $level->suppressLedgerAudit = false;
                }

                $receipt = InventoryMovement::create([
                    'inventoryable_type' => $movement->inventoryable_type,
                    'inventoryable_id' => $movement->inventoryable_id,
                    'to_location_id' => $movement->from_location_id,
                    'batch_id' => $movement->batch_id,
                    'quantity' => $movement->quantity,
                    'quantity_before' => $before,
                    'quantity_after' => $level->quantity_on_hand,
                    'type' => MovementType::Receipt->value,
                    'reason' => sprintf('order_cancelled:%s', $orderNumber),
                    'reference' => $orderId,
                    'note' => sprintf('Restored from cancelled order #%s', $orderNumber),
                    'occurred_at' => CarbonImmutable::now(),
                ]);

                if (! $receipt->exists) {
                    throw new RuntimeException('Inventory movement creation was cancelled.');
                }

                Event::dispatch(new InventoryReceived($movement->inventoryable, $level, $receipt));
            }

            return $movements->count();
        });
    }
}
