<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Widgets;

use AIArmada\Promotions\Support\PromotionPerformanceInsights;
use Filament\Widgets\ChartWidget;

final class TopPromotionsUsageChart extends ChartWidget
{
    protected static ?int $sort = 11;

    protected ?string $heading = 'Top Promotions by Orders';

    protected ?string $description = 'Promotions influencing the most orders, with usage-count fallback when order analytics are unavailable';

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $insights = app(PromotionPerformanceInsights::class);
        $topPromotions = $insights->topPromotionsByOrders();

        if ($topPromotions->isNotEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Orders Influenced',
                        'data' => $topPromotions->pluck('order_count')->all(),
                        'backgroundColor' => [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(99, 102, 241, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(249, 115, 22, 0.8)',
                            'rgba(236, 72, 153, 0.8)',
                        ],
                        'borderColor' => [
                            'rgb(59, 130, 246)',
                            'rgb(99, 102, 241)',
                            'rgb(16, 185, 129)',
                            'rgb(249, 115, 22)',
                            'rgb(236, 72, 153)',
                        ],
                        'borderWidth' => 1,
                    ],
                ],
                'labels' => $topPromotions->pluck('label')->all(),
            ];
        }

        $topPromotions = $insights->topPromotionsByUsage();

        return [
            'datasets' => [
                [
                    'label' => 'Redemptions',
                    'data' => $topPromotions->pluck('usage_count')->all(),
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                    ],
                    'borderColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(99, 102, 241)',
                        'rgb(16, 185, 129)',
                        'rgb(249, 115, 22)',
                        'rgb(236, 72, 153)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $topPromotions->pluck('label')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
