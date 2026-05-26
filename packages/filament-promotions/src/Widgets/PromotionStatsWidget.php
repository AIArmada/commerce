<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Widgets;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentPromotions\Support\PromotionPerformanceInsights;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class PromotionStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $overview = app(PromotionPerformanceInsights::class)->overview();
        $defaultCurrency = (string) config('promotions.defaults.currency', 'USD');
        $moneyCurrency = $overview['reporting_currency'] ?? $defaultCurrency;
        $influencedRevenue = $overview['currency_count'] > 1
            ? 'Mixed currencies'
            : MoneyFormatter::formatMinorWithCode($overview['influenced_revenue_minor'], $moneyCurrency);
        $discountAttributed = $overview['currency_count'] > 1
            ? 'Mixed currencies'
            : MoneyFormatter::formatMinorWithCode($overview['attributed_discount_minor'], $moneyCurrency);

        return [
            Stat::make('Total Promotions', (string) $overview['total_promotions'])
                ->description('All configured promotions')
                ->color('gray')
                ->icon('heroicon-o-sparkles'),

            Stat::make('Active Promotions', (string) $overview['active_promotions'])
                ->description($overview['code_promotions'] . ' code-based • ' . $overview['automatic_promotions'] . ' automatic')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Campaign Vouchers', (string) $overview['issued_vouchers'])
                ->description($overview['redeemed_promotion_vouchers'] . ' redeemed • ' . $overview['active_promotion_vouchers'] . ' active')
                ->color('info')
                ->icon('heroicon-o-ticket'),

            Stat::make('Orders Influenced', (string) $overview['influenced_orders'])
                ->description($overview['total_orders'] > 0
                    ? number_format($overview['influenced_order_rate'], 1) . '% of ' . number_format($overview['total_orders']) . ' orders'
                    : 'No order analytics recorded yet')
                ->color('warning')
                ->icon('heroicon-o-arrow-trending-up'),

            Stat::make('Influenced Revenue', $influencedRevenue)
                ->description($overview['currency_count'] > 1
                    ? 'Across ' . $overview['currency_count'] . ' currencies'
                    : 'Across ' . number_format($overview['influenced_orders']) . ' influenced orders')
                ->color('primary')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Discount Attributed', $discountAttributed)
                ->description($overview['code_influenced_orders'] . ' code-driven • ' . $overview['automatic_influenced_orders'] . ' automatic orders')
                ->color('info')
                ->icon('heroicon-o-ticket'),
        ];
    }
}
