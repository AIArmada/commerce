<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Services\ChipCollectService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

final class AccountBalanceWidget extends BaseWidget
{
    protected static ?int $sort = 20;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        try {
            $balance = $this->getAccountBalance();
        } catch (Throwable) {
            return [
                Stat::make('Account Balance', 'Unavailable')
                    ->description('Unable to fetch balance')
                    ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                    ->color('danger'),
            ];
        }

        return [
            Stat::make('Available Balance', $this->formatCurrency($balance['available'] ?? 0))
                ->description('Funds available for payout')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),

            Stat::make('Pending Balance', $this->formatCurrency($balance['pending'] ?? 0))
                ->description('Awaiting settlement')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning'),

            Stat::make('Reserved', $this->formatCurrency($balance['reserved'] ?? 0))
                ->description('Held for refunds/disputes')
                ->descriptionIcon(Heroicon::OutlinedShieldExclamation)
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * @return array{available: int, pending: int, reserved: int}
     */
    private function getAccountBalance(): array
    {
        $service = app(ChipCollectService::class);
        $response = $service->getAccountBalance();

        return [
            'available' => (int) ($response['available_balance'] ?? $response['available'] ?? 0),
            'pending' => (int) ($response['pending_balance'] ?? $response['pending'] ?? 0),
            'reserved' => (int) ($response['reserved_balance'] ?? $response['reserved'] ?? 0),
        ];
    }

    private function formatCurrency(int $amountInCents): string
    {
        $currency = config('filament-chip.default_currency', 'MYR');
        $amount = $amountInCents / 100;

        return sprintf('%s %s', mb_strtoupper($currency), number_format($amount, 2));
    }
}
