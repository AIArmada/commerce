<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\FilamentAffiliates\Services\AffiliateStatsAggregator;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class AffiliateStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $overview = app(AffiliateStatsAggregator::class)->overview();
        $currency = mb_strtoupper((string) config('filament-affiliates.widgets.currency', 'USD'));

        return [
            Stat::make('Affiliates', "{$overview['active_affiliates']} / {$overview['total_affiliates']}")
                ->description('Active vs total programs')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('primary'),

            Stat::make('Pending Affiliates', (string) $overview['pending_affiliates'])
                ->description('Awaiting approval')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning'),

            Stat::make('Pending Commission', $this->formatMoney($overview['pending_commission_minor'], $currency))
                ->description('Needs review')
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color('danger'),

            Stat::make('Paid Commission', $this->formatMoney($overview['paid_commission_minor'], $currency))
                ->description('Lifetime payouts')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),

            Stat::make('Conversion Rate', $overview['conversion_rate'] !== null ? number_format($overview['conversion_rate'], 1) . ' %' : '—')
                ->description('Approved vs total')
                ->descriptionIcon(Heroicon::OutlinedBolt)
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }

    private function formatMoney(int $amount, string $currency): string
    {
        return sprintf('%s %.2f', $currency, $amount / 100);
    }
}
