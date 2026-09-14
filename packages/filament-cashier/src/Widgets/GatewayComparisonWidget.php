<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\Cashier\Support\UnifiedSubscription;
use AIArmada\CashierChip\Billing\Cashier as CashierChip;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;

final class GatewayComparisonWidget extends ChartWidget
{
    protected ?string $heading = null;

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 2;

    public function getHeading(): ?string
    {
        return __('filament-cashier::dashboard.widgets.comparison.label');
    }

    /**
     * @var array{datasets: array, labels: array}|null
     */
    protected ?array $chartData = null;

    protected function getData(): array
    {
        // Memoized on the widget instance (request-bound) instead of once()
        // so owner switches within a long-lived process never leak data.
        if ($this->chartData === null) {
            $detector = app(GatewayDetector::class);
            $gateways = $detector->availableGateways();

            // Generate last 6 months labels
            $labels = collect(range(5, 0))->map(function ($monthsAgo) {
                return CarbonImmutable::now()->subMonths($monthsAgo)->format('M Y');
            })->toArray();

            $datasets = [];

            foreach ($gateways as $gateway) {
                $config = $detector->getGatewayConfig($gateway);
                $datasets[] = [
                    'label' => $config['label'],
                    'data' => $this->getMonthlyDataForGateway($gateway),
                    'borderColor' => $this->getColorValue($config['color']),
                    'backgroundColor' => $this->getColorValue($config['color'], 0.1),
                    'fill' => true,
                    'tension' => 0.3,
                ];
            }

            $this->chartData = [
                'datasets' => $datasets,
                'labels' => $labels,
            ];
        }

        return $this->chartData;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        $currency = (string) config('cashier.currency', 'USD');
        $symbol = addslashes(MoneyFormatter::symbol($currency));

        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => "function(value) { return '{$symbol}' + value.toLocaleString(); }",
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }

    /**
     * Get monthly revenue data for a gateway.
     *
     * Scans the six-month window once per gateway and buckets rows by their
     * creation month, instead of one full scan per month.
     *
     * @return list<float>
     */
    protected function getMonthlyDataForGateway(string $gateway): array
    {
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $startOfMonth = CarbonImmutable::now()->subMonths($i)->startOfMonth();
            $months[$startOfMonth->format('Y-m')] = [
                'end' => $startOfMonth->endOfMonth(),
                'revenue' => 0,
            ];
        }

        $windowStart = CarbonImmutable::now()->subMonths(5)->startOfMonth();
        $revenues = $this->getRevenueByMonth($gateway, $windowStart, $months);

        return array_map(
            static fn (int $revenue): float => round($revenue / 100, 2),
            array_values($revenues),
        );
    }

    /**
     * @param  array<string, array{end: CarbonImmutable, revenue: int}>  $months
     * @return array<string, int>
     */
    protected function getRevenueByMonth(string $gateway, DateTimeInterface $windowStart, array $months): array
    {
        $query = $this->gatewaySubscriptionsQuery($gateway);

        if ($query === null) {
            return array_map(static fn (array $month): int => $month['revenue'], $months);
        }

        $query->with('items')
            ->whereBetween('created_at', [$windowStart, CarbonImmutable::now()])
            ->chunk(200, function (Collection $subscriptions) use ($gateway, &$months): void {
                foreach ($subscriptions as $subscription) {
                    $unified = $gateway === 'stripe'
                        ? UnifiedSubscription::fromStripe($subscription)
                        : UnifiedSubscription::fromChip($subscription);

                    if (! $unified->status->isActive()) {
                        continue;
                    }

                    $bucket = $unified->createdAt->format('Y-m');

                    if (! isset($months[$bucket])) {
                        continue;
                    }

                    if ($unified->endsAt !== null && ! $unified->endsAt->isAfter($months[$bucket]['end'])) {
                        continue;
                    }

                    $months[$bucket]['revenue'] += $unified->amount;
                }
            });

        return array_map(static fn (array $month): int => $month['revenue'], $months);
    }

    protected function gatewaySubscriptionsQuery(string $gateway): ?Builder
    {
        $detector = app(GatewayDetector::class);

        if ($gateway === 'stripe' && $detector->isAvailable('stripe') && class_exists(Subscription::class)) {
            return OwnerScopedQuery::apply(Subscription::query());
        }

        if ($gateway === 'chip' && $detector->isAvailable('chip')) {
            $subscriptionModel = CashierChip::$subscriptionModel;

            return OwnerScopedQuery::apply($subscriptionModel::query());
        }

        return null;
    }

    protected function getColorValue(string $color, float $alpha = 1): string
    {
        $rgb = match ($color) {
            'primary' => '99, 102, 241',
            'success', 'emerald' => '16, 185, 129',
            'warning' => '245, 158, 11',
            'danger' => '239, 68, 68',
            'info' => '6, 182, 212',
            'indigo' => '99, 102, 241',
            'gray' => '107, 114, 128',
            default => '99, 102, 241',
        };

        return "rgba({$rgb}, {$alpha})";
    }
}
