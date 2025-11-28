<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Services;

use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Models\StockTransaction;

/**
 * Aggregates stock statistics for dashboard widgets.
 */
final class StockStatsAggregator
{
    /**
     * Get overview statistics for the stock system.
     *
     * @return array{total_transactions: int, inbound_transactions: int, outbound_transactions: int, active_reservations: int, expired_reservations: int, total_reserved_quantity: int}
     */
    public function overview(): array
    {
        $totalTransactions = StockTransaction::count();
        $inboundTransactions = StockTransaction::where('type', 'in')->count();
        $outboundTransactions = StockTransaction::where('type', 'out')->count();

        $activeReservations = StockReservation::active()->count();
        $expiredReservations = StockReservation::expired()->count();
        $totalReservedQuantity = (int) StockReservation::active()->sum('quantity');

        return [
            'total_transactions' => $totalTransactions,
            'inbound_transactions' => $inboundTransactions,
            'outbound_transactions' => $outboundTransactions,
            'active_reservations' => $activeReservations,
            'expired_reservations' => $expiredReservations,
            'total_reserved_quantity' => $totalReservedQuantity,
        ];
    }

    /**
     * Get transaction statistics for a specific period.
     *
     * @return array{inbound: int, outbound: int, net_change: int}
     */
    public function transactionStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        $inbound = (int) StockTransaction::where('type', 'in')
            ->where('transaction_date', '>=', $since)
            ->sum('quantity');

        $outbound = (int) StockTransaction::where('type', 'out')
            ->where('transaction_date', '>=', $since)
            ->sum('quantity');

        return [
            'inbound' => $inbound,
            'outbound' => $outbound,
            'net_change' => $inbound - $outbound,
        ];
    }

    /**
     * Get transactions grouped by reason.
     *
     * @return array<string, int>
     */
    public function transactionsByReason(int $days = 30): array
    {
        $since = now()->subDays($days);

        return StockTransaction::where('transaction_date', '>=', $since)
            ->selectRaw('reason, SUM(quantity) as total')
            ->groupBy('reason')
            ->pluck('total', 'reason')
            ->toArray();
    }
}
