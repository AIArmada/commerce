<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentChip\Support\PurchaseRevenueExpressions;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class PaymentMethodsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '60s';

    private const int BREAKDOWN_CACHE_TTL_SECONDS = 120;

    protected function getStats(): array
    {
        $methods = $this->getPaymentMethodBreakdown();

        $stats = [];

        foreach ($methods as $method => $data) {
            $stats[] = Stat::make($method, $data['count'])
                ->description($this->formatCurrency($data['amount']))
                ->descriptionIcon($this->getMethodIcon($method))
                ->color($this->getMethodColor($method));
        }

        if (count($stats) === 0) {
            $stats[] = Stat::make('No Payments', 0)
                ->description('No payment data available')
                ->descriptionIcon(Heroicon::CreditCard)
                ->color('gray');
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return min(count($this->getPaymentMethodBreakdown()), 4) ?: 1;
    }

    /**
     * @return array<string, array{count: int, amount: int}>
     */
    private function getPaymentMethodBreakdown(): array
    {
        /** @var array<string, array{count: int, amount: int}> $breakdown */
        $breakdown = OwnerCache::remember(
            OwnerContext::resolve(),
            'filament-chip.payment-methods',
            self::BREAKDOWN_CACHE_TTL_SECONDS,
            fn (): array => $this->computeBreakdown(),
        );

        uasort(
            $breakdown,
            static fn (array $a, array $b): int => $b['amount'] <=> $a['amount']
        );

        return array_slice($breakdown, 0, 4, true);
    }

    /**
     * Group and sum in SQL over the raw method key, then normalize labels in
     * PHP — buckets that normalize equally (e.g. `card`, `credit_card`) merge
     * with identical totals to the previous per-row computation.
     *
     * @return array<string, array{count: int, amount: int}>
     */
    private function computeBreakdown(): array
    {
        $query = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereIn('status', ['paid', 'cleared', 'settled'])
            ->where('is_test', false);

        $methodSql = PurchaseRevenueExpressions::paymentMethodKey($query);
        $totalSql = PurchaseRevenueExpressions::totalMinor($query);

        $rows = $query
            ->selectRaw("{$methodSql} AS method_key, COUNT(*) AS method_count, SUM({$totalSql}) AS method_amount")
            ->groupByRaw($methodSql)
            ->get();

        $breakdown = [];

        foreach ($rows as $row) {
            $method = $this->normalizePaymentMethod($row->getAttribute('method_key'));

            if (! isset($breakdown[$method])) {
                $breakdown[$method] = ['count' => 0, 'amount' => 0];
            }

            $breakdown[$method]['count'] += (int) $row->getAttribute('method_count');
            $breakdown[$method]['amount'] += (int) $row->getAttribute('method_amount');
        }

        return $breakdown;
    }

    private function normalizePaymentMethod(mixed $method): string
    {
        $methodValue = mb_trim((string) ($method ?? 'unknown'));
        $methodLower = mb_strtolower($methodValue);

        return match ($methodLower) {
            'fpx' => 'FPX',
            'card', 'credit_card', 'debit_card' => 'Card',
            'ewallet', 'e-wallet' => 'E-Wallet',
            'bnpl', 'buy_now_pay_later' => 'BNPL',
            'bank_transfer' => 'Bank Transfer',
            default => $methodValue !== '' ? ucfirst($methodValue) : 'Unknown',
        };
    }

    private function getMethodIcon(string $method): Heroicon
    {
        return match ($method) {
            'FPX' => Heroicon::BuildingLibrary,
            'Card' => Heroicon::CreditCard,
            'E-Wallet' => Heroicon::DevicePhoneMobile,
            'BNPL' => Heroicon::Clock,
            'Bank Transfer' => Heroicon::BuildingOffice,
            default => Heroicon::Banknotes,
        };
    }

    private function getMethodColor(string $method): string
    {
        return match ($method) {
            'FPX' => 'success',
            'Card' => 'primary',
            'E-Wallet' => 'info',
            'BNPL' => 'warning',
            'Bank Transfer' => 'gray',
            default => 'secondary',
        };
    }

    private function formatCurrency(int $amountInCents): string
    {
        return MoneyFormatter::formatMinor($amountInCents, config('filament-chip.default_currency', 'MYR'));
    }
}
