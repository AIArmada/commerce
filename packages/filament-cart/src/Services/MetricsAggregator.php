<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\Cart\Models\CartDailyMetrics;
use AIArmada\FilamentCart\Models\Cart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates raw cart data into daily metrics.
 */
class MetricsAggregator
{
    /**
     * Aggregate metrics for a specific date.
     */
    public function aggregateForDate(Carbon $date, ?string $segment = null): CartDailyMetrics
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $metrics = $this->calculateMetricsForPeriod($startOfDay, $endOfDay, $segment);

        return CartDailyMetrics::query()->forOwner()->updateOrCreate(
            [
                'date' => $date->toDateString(),
                'segment' => $segment,
            ],
            $metrics,
        );
    }

    /**
     * Aggregate totals across a date range.
     *
     * @return array<string, mixed>
     */
    public function aggregateTotals(Carbon $from, Carbon $to): array
    {
        return CartDailyMetrics::query()
            ->forOwner()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('segment')
            ->selectRaw('
                SUM(carts_created) as total_carts_created,
                SUM(carts_with_items) as total_carts_with_items,
                SUM(checkouts_started) as total_checkouts_started,
                SUM(checkouts_completed) as total_checkouts_completed,
                SUM(checkouts_abandoned) as total_checkouts_abandoned,
                SUM(carts_recovered) as total_carts_recovered,
                SUM(recovered_revenue_cents) as total_recovered_revenue,
                SUM(total_cart_value_cents) as total_cart_value,
                AVG(average_cart_value_cents) as avg_cart_value
            ')
            ->first()
            ?->toArray() ?? [];
    }

    /**
     * Backfill metrics for a date range.
     */
    public function backfill(Carbon $from, Carbon $to): int
    {
        $count = 0;
        $current = $from->copy();

        while ($current->lte($to)) {
            $this->aggregateForDate($current);
            $current->addDay();
            $count++;
        }

        return $count;
    }

    /**
     * Calculate metrics for a time period.
     *
     * @return array<string, mixed>
     */
    private function calculateMetricsForPeriod(Carbon $start, Carbon $end, ?string $segment = null): array
    {
        $baseQuery = Cart::query()->forOwner()
            ->whereBetween('created_at', [$start, $end]);

        if ($segment !== null) {
            $baseQuery->where(DB::raw($this->getJsonExtractExpression('metadata', 'segment')), $segment);
        }

        // Cart counts
        $cartsCreated = (clone $baseQuery)->count();

        $cartsWithItems = (clone $baseQuery)
            ->where(fn ($q) => $this->whereHasItems($q))
            ->count();

        $cartsEmpty = $cartsCreated - $cartsWithItems;

        // Active carts (updated in period with items)
        $cartsActive = Cart::query()->forOwner()
            ->whereBetween('updated_at', [$start, $end])
            ->where(fn ($q) => $this->whereHasItems($q))
            ->count();

        // Checkout funnel
        $checkoutsStarted = Cart::query()->forOwner()
            ->whereBetween('checkout_started_at', [$start, $end])
            ->count();

        $checkoutsCompleted = 0;

        $checkoutsAbandoned = Cart::query()->forOwner()
            ->whereBetween('checkout_abandoned_at', [$start, $end])
            ->count();

        // Recovery metrics
        $cartsRecovered = Cart::query()->forOwner()
            ->whereBetween('recovered_at', [$start, $end])
            ->count();

        $recoveredRevenue = (int) Cart::query()->forOwner()
            ->whereBetween('recovered_at', [$start, $end])
            ->sum('subtotal');

        // Value metrics
        $totalCartValue = (int) Cart::query()->forOwner()
            ->whereBetween('updated_at', [$start, $end])
            ->where(fn ($q) => $this->whereHasItems($q))
            ->sum('subtotal');

        $avgCartValue = $cartsWithItems > 0 ? (int) ($totalCartValue / $cartsWithItems) : 0;

        $totalItems = (int) Cart::query()->forOwner()
            ->whereBetween('updated_at', [$start, $end])
            ->sum('items_count');

        $avgItemsPerCart = $cartsWithItems > 0 ? $totalItems / $cartsWithItems : 0;

        return [
            'carts_created' => $cartsCreated,
            'carts_active' => $cartsActive,
            'carts_empty' => $cartsEmpty,
            'carts_with_items' => $cartsWithItems,
            'checkouts_started' => $checkoutsStarted,
            'checkouts_completed' => $checkoutsCompleted,
            'checkouts_abandoned' => $checkoutsAbandoned,
            'recovery_emails_sent' => 0, // Would need to track this separately
            'carts_recovered' => $cartsRecovered,
            'recovered_revenue_cents' => $recoveredRevenue,
            'total_cart_value_cents' => $totalCartValue,
            'average_cart_value_cents' => $avgCartValue,
            'total_items' => $totalItems,
            'average_items_per_cart' => round($avgItemsPerCart, 2),
        ];
    }

    /**
     * Add where clause for carts with items (database-agnostic).
     *
     * @param  Builder<Cart>  $query
     */
    private function whereHasItems(Builder $query): void
    {
        $driver = DB::getDriverName();

        $query->whereNotNull('items');

        if ($driver === 'pgsql') {
            $query->whereRaw("items::text != '[]'")
                ->whereRaw("items::text != 'null'");
        } else {
            $query->where('items', '!=', '[]')
                ->where('items', '!=', 'null');
        }
    }

    /**
     * Get SQL expression for JSON extraction (database-agnostic).
     */
    private function getJsonExtractExpression(string $column, string $key): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            return "{$column}->>'{$key}'";
        }

        return "JSON_EXTRACT({$column}, '\$.{$key}')";
    }
}
