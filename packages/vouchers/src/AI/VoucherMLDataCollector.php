<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Collects training data for ML model development.
 *
 * This class exports voucher application and conversion data
 * in a format suitable for training ML models externally
 * (e.g., AWS SageMaker, Python scikit-learn, etc.)
 */
final class VoucherMLDataCollector
{
    /**
     * Collect training data for conversion prediction.
     *
     * @param Carbon $from Start date
     * @param Carbon $to End date
     * @return Collection<int, array<string, mixed>>
     */
    public function collectConversionData(Carbon $from, Carbon $to): Collection
    {
        $voucherUsageTable = config('vouchers.database.tables.voucher_usage', 'voucher_usage');
        $cartsTable = config('cart.database.tables.carts', 'carts');
        $ordersTable = 'orders'; // Assumed standard table

        return DB::table($voucherUsageTable)
            ->join($cartsTable, "{$cartsTable}.id", '=', "{$voucherUsageTable}.cart_id")
            ->leftJoin($ordersTable, "{$ordersTable}.cart_id", '=', "{$cartsTable}.id")
            ->whereBetween("{$voucherUsageTable}.created_at", [$from, $to])
            ->select([
                // Identifiers
                "{$voucherUsageTable}.id as usage_id",
                "{$voucherUsageTable}.voucher_id",
                "{$voucherUsageTable}.cart_id",
                "{$voucherUsageTable}.user_id",

                // Cart features
                "{$cartsTable}.subtotal_cents as cart_value_cents",
                "{$cartsTable}.item_count",

                // Voucher application
                "{$voucherUsageTable}.discount_cents",
                DB::raw("CASE 
                    WHEN {$cartsTable}.subtotal_cents > 0 
                    THEN ({$voucherUsageTable}.discount_cents * 100.0 / {$cartsTable}.subtotal_cents) 
                    ELSE 0 
                END as discount_percentage"),

                // Time features
                DB::raw("HOUR({$voucherUsageTable}.created_at) as hour_of_day"),
                DB::raw("DAYOFWEEK({$voucherUsageTable}.created_at) as day_of_week"),

                // Target variable
                DB::raw("CASE WHEN {$ordersTable}.id IS NOT NULL THEN 1 ELSE 0 END as converted"),

                // Additional context
                "{$voucherUsageTable}.created_at as applied_at",
                "{$ordersTable}.created_at as converted_at",
            ])
            ->get();
    }

    /**
     * Collect training data for abandonment prediction.
     *
     * @param Carbon $from Start date
     * @param Carbon $to End date
     * @return Collection<int, array<string, mixed>>
     */
    public function collectAbandonmentData(Carbon $from, Carbon $to): Collection
    {
        $cartsTable = config('cart.database.tables.carts', 'carts');
        $ordersTable = 'orders';

        return DB::table($cartsTable)
            ->leftJoin($ordersTable, "{$ordersTable}.cart_id", '=', "{$cartsTable}.id")
            ->whereBetween("{$cartsTable}.created_at", [$from, $to])
            ->where("{$cartsTable}.item_count", '>', 0)
            ->select([
                // Identifiers
                "{$cartsTable}.id as cart_id",
                "{$cartsTable}.user_id",

                // Cart features
                "{$cartsTable}.subtotal_cents as cart_value_cents",
                "{$cartsTable}.item_count",
                "{$cartsTable}.conditions_count",

                // Time features
                DB::raw("HOUR({$cartsTable}.created_at) as hour_of_day"),
                DB::raw("DAYOFWEEK({$cartsTable}.created_at) as day_of_week"),

                // Cart age (if updated_at differs from created_at)
                DB::raw("TIMESTAMPDIFF(MINUTE, {$cartsTable}.created_at, {$cartsTable}.updated_at) as cart_age_minutes"),

                // Target variable (1 = abandoned, 0 = converted)
                DB::raw("CASE WHEN {$ordersTable}.id IS NULL THEN 1 ELSE 0 END as abandoned"),

                // Additional context
                "{$cartsTable}.created_at as cart_created_at",
                "{$ordersTable}.created_at as order_created_at",
            ])
            ->get();
    }

    /**
     * Collect voucher performance data for optimization.
     *
     * @param Carbon $from Start date
     * @param Carbon $to End date
     * @return Collection<int, array<string, mixed>>
     */
    public function collectVoucherPerformanceData(Carbon $from, Carbon $to): Collection
    {
        $vouchersTable = config('vouchers.database.tables.vouchers', 'vouchers');
        $voucherUsageTable = config('vouchers.database.tables.voucher_usage', 'voucher_usage');
        $ordersTable = config('vouchers.database.tables.orders', 'orders');

        return DB::table($vouchersTable)
            ->leftJoin($voucherUsageTable, "{$voucherUsageTable}.voucher_id", '=', "{$vouchersTable}.id")
            ->leftJoin($ordersTable, function ($join) use ($voucherUsageTable, $ordersTable) {
                $join->on("{$ordersTable}.cart_id", '=', "{$voucherUsageTable}.cart_id");
            })
            ->whereBetween("{$vouchersTable}.created_at", [$from, $to])
            ->groupBy("{$vouchersTable}.id")
            ->select([
                // Voucher info
                "{$vouchersTable}.id as voucher_id",
                "{$vouchersTable}.code",
                "{$vouchersTable}.type",
                "{$vouchersTable}.value",
                "{$vouchersTable}.min_cart_value",
                "{$vouchersTable}.usage_limit",

                // Performance metrics
                DB::raw("COUNT({$voucherUsageTable}.id) as total_applications"),
                DB::raw("COUNT({$ordersTable}.id) as conversions"),
                DB::raw("SUM({$voucherUsageTable}.discount_cents) as total_discount_given"),
                DB::raw("AVG({$voucherUsageTable}.discount_cents) as avg_discount"),

                // Conversion rate
                DB::raw("CASE 
                    WHEN COUNT({$voucherUsageTable}.id) > 0 
                    THEN COUNT({$ordersTable}.id) * 1.0 / COUNT({$voucherUsageTable}.id) 
                    ELSE 0 
                END as conversion_rate"),
            ])
            ->get();
    }

    /**
     * Export data to CSV format.
     *
     * @param Collection $data
     * @param string $filepath
     */
    public function exportToCsv(Collection $data, string $filepath): void
    {
        if ($data->isEmpty()) {
            return;
        }

        $handle = fopen($filepath, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for writing: {$filepath}");
        }

        // Write headers
        fputcsv($handle, array_keys((array) $data->first()));

        // Write data rows
        foreach ($data as $row) {
            fputcsv($handle, (array) $row);
        }

        fclose($handle);
    }

    /**
     * Export data to JSON format.
     *
     * @param Collection $data
     * @param string $filepath
     */
    public function exportToJson(Collection $data, string $filepath): void
    {
        file_put_contents(
            $filepath,
            json_encode($data->toArray(), JSON_PRETTY_PRINT)
        );
    }

    /**
     * Get summary statistics for the collected data.
     *
     * @param Collection $data
     * @return array<string, mixed>
     */
    public function getSummaryStatistics(Collection $data): array
    {
        if ($data->isEmpty()) {
            return [
                'count' => 0,
                'columns' => [],
            ];
        }

        $first = (array) $data->first();
        $columns = [];

        foreach (array_keys($first) as $column) {
            $values = $data->pluck($column)->filter(fn ($v) => is_numeric($v));

            if ($values->isNotEmpty()) {
                $columns[$column] = [
                    'type' => 'numeric',
                    'min' => $values->min(),
                    'max' => $values->max(),
                    'avg' => round($values->avg(), 2),
                    'count' => $values->count(),
                ];
            } else {
                $uniqueValues = $data->pluck($column)->unique()->count();
                $columns[$column] = [
                    'type' => 'categorical',
                    'unique_values' => $uniqueValues,
                    'count' => $data->count(),
                ];
            }
        }

        return [
            'count' => $data->count(),
            'columns' => $columns,
        ];
    }
}
