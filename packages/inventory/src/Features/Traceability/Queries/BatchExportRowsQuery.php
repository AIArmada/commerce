<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Queries;

use AIArmada\Inventory\Features\Traceability\Api\BatchExportRows;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;

final readonly class BatchExportRowsQuery implements BatchExportRows
{
    public function rows(?string $status, bool $expiringOnly, int $expiringWithinDays): iterable
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryBatch::query()->with('location:id,name')
        );

        if ($status !== null) {
            $query->where('status', $status);
        }

        $now = CarbonImmutable::now();

        if ($expiringOnly) {
            $expiryDate = $now->addDays($expiringWithinDays);
            $query->whereNotNull('expires_at')
                ->where('expires_at', '<=', $expiryDate)
                ->where('expires_at', '>', $now);
        }

        foreach ($query->lazyById(500) as $batch) {
            $daysUntilExpiry = $batch->expires_at !== null
                ? $now->diffInDays($batch->expires_at, false)
                : null;

            yield [
                $batch->batch_number,
                $batch->inventoryable_type,
                $batch->inventoryable_id,
                $batch->location->name ?? 'Unknown',
                $batch->quantity_on_hand,
                $batch->status,
                $batch->unit_cost_minor !== null ? $batch->unit_cost_minor / 100 : 0,
                ($batch->quantity_on_hand * ($batch->unit_cost_minor ?? 0)) / 100,
                $batch->manufactured_at?->format('Y-m-d'),
                $batch->expires_at?->format('Y-m-d'),
                $daysUntilExpiry,
                $batch->supplier_id,
                $batch->purchase_order_number,
                $batch->metadata['notes'] ?? null,
                $batch->created_at->format('Y-m-d H:i:s'),
            ];
        }
    }
}
