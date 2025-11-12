<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\Vouchers\Models\VoucherWallet;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class VoucherWalletStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $total = VoucherWallet::count();
        $claimed = VoucherWallet::where('is_claimed', true)->count();
        $redeemed = VoucherWallet::where('is_redeemed', true)->count();
        $available = VoucherWallet::where('is_redeemed', false)->count();

        // Calculate unique vouchers in wallets
        $uniqueVouchers = VoucherWallet::distinct('voucher_id')->count('voucher_id');

        // Calculate unique owners (users/stores/teams) who have vouchers in their wallets
        $uniqueOwners = VoucherWallet::selectRaw("COUNT(DISTINCT CONCAT(owner_type, '-', owner_id)) as count")
            ->value('count') ?? 0;

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

            Stat::make('Unique Owners', $uniqueOwners)
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
     */
    private function getWalletTrend(): array
    {
        $data = [];
        $now = now();

        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->startOfDay();
            $count = VoucherWallet::whereDate('created_at', $date)->count();
            $data[] = $count;
        }

        return $data;
    }
}
