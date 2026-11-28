<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use AIArmada\Inventory\States\Available;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Support\Facades\DB;

final readonly class DeleteTraceabilityRecord
{
    public function execute(InventoryBatch | InventorySerial $record): bool
    {
        return DB::transaction(function () use ($record): bool {
            if ($record instanceof InventoryBatch) {
                $query = InventoryBatch::query();

                if (InventoryOwnerScope::isEnabled()) {
                    $owner = InventoryOwnerScope::resolveOwner();
                    OwnerContext::assertResolvedOrExplicitGlobal($owner);
                    $query->forOwner($owner, false);
                }

                $batch = $query->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

                if ($batch->quantity_on_hand !== 0 || $batch->quantity_reserved !== 0
                    || $batch->quantity_received !== 0 || $batch->hasTraceabilityReferences()) {
                    return false;
                }

                return (bool) $batch->delete();
            }

            $query = InventorySerial::query();

            if (InventoryOwnerScope::isEnabled()) {
                $owner = InventoryOwnerScope::resolveOwner();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $serial = $query->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! $serial->status instanceof Available || $serial->order_id !== null
                || $serial->customer_id !== null || $serial->sold_at !== null || $serial->history()->withoutGlobalScopes()->exists()) {
                return false;
            }

            return (bool) $serial->delete();
        }, 3);
    }
}
