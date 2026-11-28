<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Replenishment\Actions;

use AIArmada\Inventory\Features\Replenishment\Models\InventorySupplierLeadtime;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSupplierLeadtime
{
    public function markAsPrimary(InventorySupplierLeadtime $leadtime): bool
    {
        return DB::transaction(function () use ($leadtime): bool {
            $target = InventoryOwnerScope::forMutation(InventorySupplierLeadtime::query())
                ->whereKey($leadtime->getKey())
                ->firstOrFail();

            // Lock the complete supplier set in a consistent order before changing its primary.
            $suppliers = InventoryOwnerScope::forMutation(InventorySupplierLeadtime::query())
                ->where('inventoryable_type', $target->inventoryable_type)
                ->where('inventoryable_id', $target->inventoryable_id)
                ->where('owner_type', $target->owner_type)
                ->where('owner_id', $target->owner_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $locked = $suppliers->findOrFail($target->getKey());

            InventoryOwnerScope::forMutation(InventorySupplierLeadtime::query())
                ->whereKey($suppliers->modelKeys())
                ->whereKeyNot($locked->getKey())
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            return $locked->update(['is_primary' => true]);
        }, 3);
    }

    public function recordOrder(InventorySupplierLeadtime $leadtime): bool
    {
        return DB::transaction(fn (): bool => $this->lock($leadtime)->update([
            'last_order_at' => CarbonImmutable::now(),
        ]), 3);
    }

    public function recordReceipt(InventorySupplierLeadtime $leadtime): bool
    {
        return DB::transaction(fn (): bool => $this->lock($leadtime)->update([
            'last_received_at' => CarbonImmutable::now(),
        ]), 3);
    }

    private function lock(InventorySupplierLeadtime $leadtime): InventorySupplierLeadtime
    {
        return InventoryOwnerScope::forMutation(InventorySupplierLeadtime::query())
            ->whereKey($leadtime->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
