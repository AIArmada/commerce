<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Stock\Api\RestoreOrderStock as RestoreOrderStockContract;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class RestoreOrderStock implements RestoreOrderStockContract
{
    public function __construct(private ReceiveInventory $receive) {}

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

                $this->receive->handle(
                    model: $movement->inventoryable,
                    locationId: $movement->from_location_id,
                    quantity: $movement->quantity,
                    reason: sprintf('order_cancelled:%s', $orderNumber),
                    note: sprintf('Restored from cancelled order #%s', $orderNumber),
                );
            }

            return $movements->count();
        });
    }
}
