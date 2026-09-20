<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentAffiliates\Services\AffiliateStatsAggregator;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class AffiliateStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        /** @var array{active_affiliates: int, total_affiliates: int, pending_affiliates: int, pending_commission_minor: int|null, paid_commission_minor: int|null, commission_currency: string, commission_converted: bool, conversion_rate: float|null} $overview */
        $overview = app(AffiliateStatsAggregator::class)->overview();
        $currency = $overview['commission_currency'];

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
                ->description($this->commissionDescription($overview, 'Needs review'))
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color('danger'),

            Stat::make('Paid Commission', $this->formatMoney($overview['paid_commission_minor'], $currency))
                ->description($this->commissionDescription($overview, 'Lifetime payouts'))
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

    private function formatMoney(?int $amount, string $currency): string
    {
        if ($amount === null) {
            return '—';
        }

        return MoneyFormatter::formatMinor($amount, $currency);
    }

    /**
     * @param  array{pending_commission_minor: int|null, paid_commission_minor: int|null, commission_currency: string, commission_converted: bool}  $overview
     */
    private function commissionDescription(array $overview, string $default): string
    {
        if ($overview['pending_commission_minor'] === null || $overview['paid_commission_minor'] === null) {
            return 'Mixed currencies — set exchange rates';
        }

        if ($overview['commission_converted']) {
            return $default . ' (converted to ' . $overview['commission_currency'] . ')';
        }

        return $default;
    }
}
