<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\Cashier\Queries\BillingAnalyticsQuery;
use AIArmada\Cashier\Support\BillingCurrency;
use AIArmada\Cashier\Support\GatewayDetector;
use Filament\Widgets\ChartWidget;

final class GatewayBreakdownWidget extends ChartWidget
{
    protected ?string $heading = null;

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    public function getHeading(): ?string
    {
        return __('filament-cashier::dashboard.widgets.gateway_breakdown.label');
    }

    protected function getData(): array
    {
        $detector = app(GatewayDetector::class);
        $revenueByGateway = $this->getRevenueByGateway();

        $labels = [];
        $data = [];
        $backgroundColor = [];

        foreach ($revenueByGateway as $gateway => $amount) {
            $config = $detector->getGatewayConfig($gateway);
            $labels[] = $config['label'];
            $data[] = BillingCurrency::major($amount, (string) config('filament-cashier.currency.base', 'MYR'));
            $backgroundColor[] = $this->getColorValue($config['color']);
        }

        return [
            'datasets' => [
                [
                    'label' => 'MRR (' . config('filament-cashier.currency.base', 'MYR') . ')',
                    'data' => $data,
                    'backgroundColor' => $backgroundColor,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'maintainAspectRatio' => true,
        ];
    }

    /** @return array<string, int> */
    protected function getRevenueByGateway(): array
    {
        $summary = app(BillingAnalyticsQuery::class)->activeSummary();
        $base = (string) config('filament-cashier.currency.base', 'MYR');
        $convert = (bool) config('filament-cashier.currency.display_converted', false);
        $rates = (array) config('filament-cashier.currency.conversion_rates', []);
        $totals = [];

        foreach ($summary['mrrByGateway'] as $gateway => $amounts) {
            $totals[$gateway] = BillingCurrency::total($amounts, $base, $convert, $rates);
        }

        return $totals;
    }

    protected function getColorValue(string $color): string
    {
        return match ($color) {
            'primary' => 'rgb(99, 102, 241)',
            'success', 'emerald' => 'rgb(16, 185, 129)',
            'warning' => 'rgb(245, 158, 11)',
            'danger' => 'rgb(239, 68, 68)',
            'info' => 'rgb(6, 182, 212)',
            'indigo' => 'rgb(99, 102, 241)',
            'gray' => 'rgb(107, 114, 128)',
            default => 'rgb(99, 102, 241)',
        };
    }
}
