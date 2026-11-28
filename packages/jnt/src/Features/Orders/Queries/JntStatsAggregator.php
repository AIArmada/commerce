<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Queries;

use AIArmada\Jnt\Features\Orders\Api\DashboardStats;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;

final readonly class JntStatsAggregator implements DashboardStats
{
    /**
     * @return array{total:int, delivered:int, in_transit:int, problems:int, pending:int, returns:int}
     */
    public function calculateOrderStats(): array
    {
        $query = JntOrder::query()->toBase();

        $row = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NULL AND tracking_number IS NOT NULL AND problem_at IS NULL THEN 1 ELSE 0 END) as in_transit')
            ->selectRaw('SUM(CASE WHEN problem_at IS NOT NULL THEN 1 ELSE 0 END) as problems')
            ->selectRaw('SUM(CASE WHEN tracking_number IS NULL THEN 1 ELSE 0 END) as pending')
            ->selectRaw("SUM(CASE WHEN last_status_code IN ('172','173') THEN 1 ELSE 0 END) as returns")
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'delivered' => (int) ($row?->delivered ?? 0),
            'in_transit' => (int) ($row?->in_transit ?? 0),
            'problems' => (int) ($row?->problems ?? 0),
            'pending' => (int) ($row?->pending ?? 0),
            'returns' => (int) ($row?->returns ?? 0),
        ];
    }
}
