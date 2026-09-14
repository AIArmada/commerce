<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\Cashier\Support\UnifiedSubscription;
use AIArmada\CashierChip\Billing\Cashier as CashierChip;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;

final class TotalMrrWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $summary = $this->getActiveSubscriptionsSummary();

        // Calculate MRR by currency
        $mrrByCurrency = collect($summary['mrrByCurrency']);

        // Primary MRR (use base currency from config or largest)
        $baseCurrency = config('filament-cashier.currency.base', 'USD');
        $primaryMrr = $mrrByCurrency->get($baseCurrency, 0);

        // Convert other currencies if enabled. Rates are quoted per USD, so a
        // source amount converts via amount / rate[source] * rate[base].
        if (config('filament-cashier.currency.display_converted', false)) {
            $rates = config('filament-cashier.currency.conversion_rates', []);
            foreach ($mrrByCurrency as $currency => $amount) {
                $primaryMrr += $this->convertAmountToBase((int) $amount, (string) $currency, $baseCurrency, $rates);
            }
        }

        $formattedMrr = $this->formatCurrency($primaryMrr, $baseCurrency);

        // Calculate trend (mock for now - would need historical data)
        $trend = $summary['count'] > 0 ? '+12%' : '0%';

        return [
            Stat::make(__('filament-cashier::dashboard.widgets.total_mrr.label'), $formattedMrr)
                ->description(__('filament-cashier::dashboard.widgets.total_mrr.description'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 3, 4, 5, 6, 3, 5, 8])
                ->color('success'),
        ];
    }

    /**
     * Convert a source-currency amount into the base currency.
     *
     * @param  array<string, float|int>  $rates  Currency units per USD.
     */
    protected function convertAmountToBase(int $amount, string $currency, string $baseCurrency, array $rates): int
    {
        if ($currency === $baseCurrency) {
            return 0;
        }

        $sourceRate = $rates[$currency] ?? null;
        $baseRate = $rates[$baseCurrency] ?? null;

        if (! is_numeric($sourceRate) || (float) $sourceRate <= 0) {
            return 0;
        }

        if (! is_numeric($baseRate) || (float) $baseRate <= 0) {
            return 0;
        }

        return (int) ($amount * ((float) $baseRate / (float) $sourceRate));
    }

    /**
     * @var array{count: int, mrrByCurrency: array<string, int>}|null
     */
    protected ?array $subscriptionsSummary = null;

    /**
     * Get active subscriptions across all gateways.
     *
     * Memoized on the widget instance (request-bound) instead of once() so
     * owner switches within a long-lived process never leak cached totals.
     *
     * @return array{count: int, mrrByCurrency: array<string, int>}
     */
    protected function getActiveSubscriptionsSummary(): array
    {
        if ($this->subscriptionsSummary === null) {
            $count = 0;
            $mrrByCurrency = [];
            $detector = app(GatewayDetector::class);

            if ($detector->isAvailable('stripe') && class_exists(Subscription::class)) {
                $stripeQuery = OwnerScopedQuery::apply(Subscription::query())
                    ->with('items')
                    ->where(function ($query): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', CarbonImmutable::now());
                    });

                $stripeQuery->chunk(200, function (Collection $chunk) use (&$count, &$mrrByCurrency): void {
                    foreach ($chunk as $model) {
                        $unified = UnifiedSubscription::fromStripe($model);

                        if (! $unified->status->isActive()) {
                            continue;
                        }

                        $mrrByCurrency[$unified->currency] = ($mrrByCurrency[$unified->currency] ?? 0) + $unified->amount;
                        $count++;
                    }
                });
            }

            if ($detector->isAvailable('chip')) {
                $subscriptionModel = CashierChip::$subscriptionModel;
                $chipQuery = OwnerScopedQuery::apply($subscriptionModel::query())
                    ->with('items')
                    ->where(function ($query): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', CarbonImmutable::now());
                    });

                $chipQuery->chunk(200, function (Collection $chunk) use (&$count, &$mrrByCurrency): void {
                    foreach ($chunk as $model) {
                        $unified = UnifiedSubscription::fromChip($model);

                        if (! $unified->status->isActive()) {
                            continue;
                        }

                        $mrrByCurrency[$unified->currency] = ($mrrByCurrency[$unified->currency] ?? 0) + $unified->amount;
                        $count++;
                    }
                });
            }

            $this->subscriptionsSummary = [
                'count' => $count,
                'mrrByCurrency' => $mrrByCurrency,
            ];
        }

        return $this->subscriptionsSummary;
    }

    protected function formatCurrency(int $amountInCents, string $currency): string
    {
        return MoneyFormatter::formatMinor($amountInCents, $currency);
    }
}
