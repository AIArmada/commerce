<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\FilamentVouchers\Services\VoucherStatsAggregator;
use Akaunting\Money\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class VoucherStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $overview = app(VoucherStatsAggregator::class)->overview();
        $currency = mb_strtoupper(config('filament-vouchers.default_currency', 'MYR'));

        return [
            Stat::make('Total Vouchers', $overview['total'])
                ->description('All vouchers in the system')
                ->descriptionIcon(Heroicon::Ticket)
                ->color('primary'),

            Stat::make('Active Vouchers', $overview['active'])
                ->description('Currently redeemable')
                ->descriptionIcon(Heroicon::Bolt)
                ->color('success'),

            Stat::make('Upcoming Launches', $overview['upcoming'])
                ->description('Scheduled to activate')
                ->descriptionIcon(Heroicon::Calendar)
                ->color('info'),

            Stat::make('Manual Redemptions', $overview['manual_redemptions'])
                ->description('Processed by staff')
                ->descriptionIcon(Heroicon::UserGroup)
                ->color('warning'),

            Stat::make('Discount Granted', $this->formatMoney($overview['total_discount_minor'], $currency))
                ->description('Total value redeemed')
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('success'),
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }

    private function formatMoney(int $amount, string $currency): string
    {
        return (string) Money::{$currency}($amount);
    }
}
