<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherWallet;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;

final class VoucherWalletStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        /** @var array{total: int, claimed: int, redeemed: int, available: int, unique_vouchers: int, unique_holders: int} $counts */
        $counts = OwnerCache::remember(
            OwnerContext::resolve(),
            'filament-vouchers.wallet-stats',
            30,
            function (): array {
                $wallets = $this->wallets();

                return [
                    'total' => (clone $wallets)->count(),
                    'claimed' => (clone $wallets)->whereNotNull('claimed_at')->count(),
                    'redeemed' => (clone $wallets)->whereNotNull('redeemed_at')->count(),
                    'available' => (clone $wallets)->whereNull('redeemed_at')->count(),
                    'unique_vouchers' => (clone $wallets)->distinct('voucher_id')->count('voucher_id'),
                    'unique_holders' => $this->distinctHolderCount(clone $wallets),
                ];
            },
        );

        $total = $counts['total'];
        $claimed = $counts['claimed'];
        $redeemed = $counts['redeemed'];
        $available = $counts['available'];

        // Calculate unique vouchers in wallets
        $uniqueVouchers = $counts['unique_vouchers'];

        // Calculate unique holders (users/stores/teams) who have vouchers in their wallets
        $uniqueOwners = $counts['unique_holders'];

        return [
            Stat::make('Total Wallet Entries', $total)
                ->description('Vouchers saved to wallets')
                ->descriptionIcon(Heroicon::Ticket)
                ->color('primary')
                ->chart($this->getWalletTrend()),

            Stat::make('Unique Vouchers', $uniqueVouchers)
                ->description('Different vouchers in wallets')
                ->descriptionIcon(Heroicon::Sparkles)
                ->color('info'),

            Stat::make('Unique Holders', $uniqueOwners)
                ->description('Users with saved vouchers')
                ->descriptionIcon(Heroicon::UserGroup)
                ->color('success'),

            Stat::make('Available', $available)
                ->description('Ready to be used')
                ->descriptionIcon(Heroicon::CheckCircle)
                ->color('success'),

            Stat::make('Claimed', $claimed)
                ->description('Claimed by owners')
                ->descriptionIcon(Heroicon::ShieldCheck)
                ->color('warning'),

            Stat::make('Redeemed', $redeemed)
                ->description('Already used')
                ->descriptionIcon(Heroicon::CheckBadge)
                ->color('danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * Get wallet entries trend for the last 7 days.
     *
     * @return array<int, int>
     */
    private function getWalletTrend(): array
    {
        $now = CarbonImmutable::now();
        $startDate = $now->copy()->subDays(6)->startOfDay();

        /** @var Connection $connection */
        $connection = VoucherWallet::query()->getConnection();
        $driver = ConnectionDriver::name($connection);
        $dateExpression = match ($driver) {
            'sqlite' => 'date(created_at)',
            default => 'DATE(created_at)',
        };

        /** @var array<string, int> $countsByDate */
        $countsByDate = $this->wallets()
            ->selectRaw("{$dateExpression} as date, COUNT(*) as count")
            ->where('created_at', '>=', $startDate)
            ->groupByRaw($dateExpression)
            ->pluck('count', 'date')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->format('Y-m-d');
            $data[] = $countsByDate[$date] ?? 0;
        }

        return $data;
    }

    private function distinctHolderCount(Builder $wallets): int
    {
        /** @var Connection $connection */
        $connection = VoucherWallet::query()->getConnection();
        $driver = ConnectionDriver::name($connection);
        $concat = $driver === 'pgsql' || $driver === 'sqlite'
            ? "holder_type || '-' || holder_id"
            : "CONCAT(holder_type, '-', holder_id)";

        return (int) $wallets->selectRaw("COUNT(DISTINCT {$concat}) as count")
            ->value('count');
    }

    /**
     * @return Builder<VoucherWallet>
     */
    private function wallets(): Builder
    {
        /** @var Builder<VoucherWallet> $query */
        $query = VoucherWallet::query();

        if (! config('vouchers.owner.enabled', false)) {
            return $query;
        }

        $voucherQuery = Voucher::query()->select('id');

        $voucherQuery = OwnerQuery::applyToEloquentBuilder(
            $voucherQuery,
            OwnerContext::resolve(),
            (bool) config('vouchers.owner.include_global', false),
        );

        return $query->whereIn('voucher_id', $voucherQuery);
    }
}
