<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class UpdateTraceabilityDetails
{
    /** @param array<string, mixed> $attributes */
    public function batch(InventoryBatch $batch, array $attributes): InventoryBatch
    {
        return DB::transaction(function () use ($batch, $attributes): InventoryBatch {
            $query = InventoryBatch::query();

            if (InventoryOwnerScope::isEnabled()) {
                $owner = InventoryOwnerScope::resolveOwner();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $record = $query->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            $details = Arr::only($attributes, [
                'batch_number', 'lot_number', 'supplier_batch_number',
                'manufactured_at', 'received_at', 'expires_at', 'unit_cost_minor', 'metadata',
            ]);

            $this->assertCost($details);

            if (isset($details['metadata']) && is_array($details['metadata'])) {
                $details['metadata'] = array_replace($record->metadata ?? [], $details['metadata']);
            }

            $record->update($details);

            return $record;
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function serial(InventorySerial $serial, array $attributes): InventorySerial
    {
        return DB::transaction(function () use ($serial, $attributes): InventorySerial {
            $query = InventorySerial::query();

            if (InventoryOwnerScope::isEnabled()) {
                $owner = InventoryOwnerScope::resolveOwner();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $record = $query->whereKey($serial->getKey())->lockForUpdate()->firstOrFail();
            $details = Arr::only($attributes, ['serial_number', 'unit_cost_minor', 'received_at']);
            $this->assertCost($details);
            $record->update($details);

            return $record;
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    private function assertCost(array $attributes): void
    {
        if (isset($attributes['unit_cost_minor']) && (int) $attributes['unit_cost_minor'] < 0) {
            throw new InvalidArgumentException('Unit cost cannot be negative');
        }
    }
}
