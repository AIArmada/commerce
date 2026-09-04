<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class TokenStatsWidget extends BaseWidget
{
    protected static ?int $sort = 30;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = $this->getTokenStats();

        return [
            Stat::make('Active Tokens', (string) $stats['active_tokens'])
                ->description('Recurring payment tokens')
                ->descriptionIcon(Heroicon::Key)
                ->color('success'),

            Stat::make('Token Purchases', (string) $stats['token_purchases'])
                ->description('Payments using tokens')
                ->descriptionIcon(Heroicon::ArrowPath)
                ->color('primary'),

            Stat::make('Token Revenue', $this->formatCurrency($stats['token_revenue']))
                ->description('Revenue from recurring')
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * @return array{active_tokens: int, token_purchases: int, token_revenue: int}
     */
    private function getTokenStats(): array
    {
        $activeTokens = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereNotNull('recurring_token')
            ->whereIn('status', ['paid', 'cleared', 'settled'])
            ->distinct('recurring_token')
            ->count();

        $tokenPurchases = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereNotNull('recurring_token')
            ->whereIn('status', ['paid', 'cleared', 'settled'])
            ->count();

        $tokenRevenue = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereNotNull('recurring_token')
            ->whereIn('status', ['paid', 'cleared', 'settled'])
            ->get()
            ->sum(function (Purchase $purchase): int {
                return (int) ($purchase->purchase['total'] ?? 0);
            });

        return [
            'active_tokens' => $activeTokens,
            'token_purchases' => $tokenPurchases,
            'token_revenue' => $tokenRevenue,
        ];
    }

    private function formatCurrency(int $amountInCents): string
    {
        return MoneyFormatter::formatMinor($amountInCents, config('filament-chip.default_currency', 'MYR'));
    }
}
