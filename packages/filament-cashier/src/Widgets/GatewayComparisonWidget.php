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

    protected function getData(): array
    {
        return once(function (): array {
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

            return [
                'datasets' => $datasets,
                'labels' => $labels,
            ];
        });
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
     * @return list<float>
     */
    protected function getMonthlyDataForGateway(string $gateway): array
    {
        $data = [];

        // Generate data for last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $startOfMonth = CarbonImmutable::now()->subMonths($i)->startOfMonth();
            $endOfMonth = CarbonImmutable::now()->subMonths($i)->endOfMonth();

            $revenue = $this->getRevenueForPeriod($gateway, $startOfMonth, $endOfMonth);
            $data[] = round($revenue / 100, 2);
        }

        return $data;
    }

    protected function getRevenueForPeriod(string $gateway, DateTimeInterface $start, DateTimeInterface $end): int
    {
        $detector = app(GatewayDetector::class);

        if ($gateway === 'stripe' && $detector->isAvailable('stripe') && class_exists(Subscription::class)) {
            $revenue = 0;

            OwnerScopedQuery::apply(Subscription::query())
                ->with('items')
                ->whereBetween('created_at', [$start, $end])
                ->where(function ($query) use ($end): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', $end);
                })
                ->chunk(200, function (Collection $subscriptions) use (&$revenue): void {
                    foreach ($subscriptions as $subscription) {
                        $unified = UnifiedSubscription::fromStripe($subscription);

                        if ($unified->status->isActive()) {
                            $revenue += $unified->amount;
                        }
                    }
                });

            return $revenue;
        }

        if ($gateway === 'chip' && $detector->isAvailable('chip')) {
            $subscriptionModel = CashierChip::$subscriptionModel;
            $revenue = 0;

            OwnerScopedQuery::apply($subscriptionModel::query())
                ->with('items')
                ->whereBetween('created_at', [$start, $end])
                ->where(function ($query) use ($end): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', $end);
                })
                ->chunk(200, function (Collection $subscriptions) use (&$revenue): void {
                    foreach ($subscriptions as $subscription) {
                        $unified = UnifiedSubscription::fromChip($subscription);

                        if ($unified->status->isActive()) {
                            $revenue += $unified->amount;
                        }
                    }
                });

            return $revenue;
        }

        return 0;
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
