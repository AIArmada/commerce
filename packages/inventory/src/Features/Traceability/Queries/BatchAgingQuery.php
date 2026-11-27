<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Queries;

use AIArmada\Inventory\Features\Traceability\Api\BatchAging;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;

final readonly class BatchAgingQuery implements BatchAging
{
    /** @return list<array{age_range: string, batch_count: int, total_quantity: int, total_value: int, expiring_soon: int}> */
    public function summarize(): array
    {
        $now = CarbonImmutable::now();
        $expiryCutoff = $now->addDays(30);
        $ranges = [
            '0-30 days' => [0, 30],
            '31-60 days' => [31, 60],
            '61-90 days' => [61, 90],
            '91-180 days' => [91, 180],
            '181-365 days' => [181, 365],
            'Over 1 year' => [366, PHP_INT_MAX],
        ];
        $buckets = [];

        foreach (array_keys($ranges) as $label) {
            $buckets[$label] = [
                'age_range' => $label,
                'batch_count' => 0,
                'total_quantity' => 0,
                'total_value' => 0,
                'expiring_soon' => 0,
            ];
        }

        $query = InventoryOwnerScope::applyToLocationQuery(InventoryBatch::query())
            ->whereNotNull('manufactured_at')
            ->where('manufactured_at', '<=', $now);

        foreach ($query->lazyById() as $batch) {
            if ($batch->manufactured_at === null) {
                continue;
            }

            $age = (int) $batch->manufactured_at->diffInDays($now);

            foreach ($ranges as $label => [$minimum, $maximum]) {
                if ($age < $minimum || $age > $maximum) {
                    continue;
                }

                $buckets[$label]['batch_count']++;
                $buckets[$label]['total_quantity'] += $batch->quantity_on_hand;
                $buckets[$label]['total_value'] += $batch->quantity_on_hand * ($batch->unit_cost_minor ?? 0);

                if ($batch->expires_at !== null && $batch->expires_at >= $now && $batch->expires_at <= $expiryCutoff) {
                    $buckets[$label]['expiring_soon']++;
                }

                break;
            }
        }

        return array_values($buckets);
    }
}
