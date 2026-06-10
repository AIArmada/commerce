<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Jnt\Models\JntOrder;
use Illuminate\Database\Eloquent\Builder;

final class JntStatsAggregator
{
    /**
     * @return array{total:int, delivered:int, in_transit:int, problems:int, pending:int, returns:int}
     */
    public static function calculateOrderStats(): array
    {
        $query = self::ordersQuery();

        /** @var JntOrder|null $row */
        $row = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NULL AND tracking_number IS NOT NULL AND problem_at IS NULL THEN 1 ELSE 0 END) as in_transit')
            ->selectRaw('SUM(CASE WHEN problem_at IS NOT NULL THEN 1 ELSE 0 END) as problems')
            ->selectRaw('SUM(CASE WHEN tracking_number IS NULL THEN 1 ELSE 0 END) as pending')
            ->selectRaw("SUM(CASE WHEN last_status_code IN ('172','173') THEN 1 ELSE 0 END) as returns")
            ->first();

        return [
            'total' => (int) ($row?->getAttribute('total') ?? 0),
            'delivered' => (int) ($row?->getAttribute('delivered') ?? 0),
            'in_transit' => (int) ($row?->getAttribute('in_transit') ?? 0),
            'problems' => (int) ($row?->getAttribute('problems') ?? 0),
            'pending' => (int) ($row?->getAttribute('pending') ?? 0),
            'returns' => (int) ($row?->getAttribute('returns') ?? 0),
        ];
    }

    /**
     * @return Builder<JntOrder>
     */
    private static function ordersQuery(): Builder
    {
        /** @var Builder<JntOrder> $query */
        $query = JntOrder::query();

        if (! (bool) config('jnt.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = (bool) config('jnt.owner.include_global', false);

        return OwnerUiScope::apply($query, includeGlobal: $includeGlobal);
    }
}
