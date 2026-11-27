<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;
use RuntimeException;
use Throwable;

final class AccountBalanceWidget extends BaseWidget
{
    protected static ?int $sort = 20;

    #[Override]
    public static function canView(): bool
    {
        if (! (bool) config('chip.owner.enabled', false)) {
            return true;
        }

        return OwnerContext::resolve() === null && OwnerContext::isExplicitGlobal();
    }

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
        if ((bool) config('chip.owner.enabled', false)
            && (OwnerContext::resolve() !== null || ! OwnerContext::isExplicitGlobal())) {
            throw new RuntimeException('Company account balance requires explicit global owner context.');
        }

        /** @var array{available: int, pending: int, reserved: int} $balance */
        $balance = OwnerCache::remember(
            OwnerContext::resolve(),
            'filament-chip.account-balance',
            max(1, (int) config('filament-chip.widgets.balance_cache_ttl', 60)),
            function (): array {
                $response = app(ChipCollectService::class)->getAccountBalance();

                return [
                    'available' => (int) ($response['available_balance'] ?? $response['available'] ?? 0),
                    'pending' => (int) ($response['pending_balance'] ?? $response['pending'] ?? 0),
                    'reserved' => (int) ($response['reserved_balance'] ?? $response['reserved'] ?? 0),
                ];
            },
        );

        return $balance;
    }

    private function formatCurrency(int $amountInCents): string
    {
        return MoneyFormatter::formatMinor($amountInCents, config('filament-chip.default_currency', 'MYR'));
    }
}
