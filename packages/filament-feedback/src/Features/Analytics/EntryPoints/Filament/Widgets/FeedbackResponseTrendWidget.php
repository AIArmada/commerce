<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets;

use AIArmada\FilamentFeedback\Features\Analytics\Queries\FeedbackDashboardMemo;
use Filament\Widgets\ChartWidget;

final class FeedbackResponseTrendWidget extends ChartWidget
{
    protected function getData(): array
    {
        $daily = app(FeedbackDashboardMemo::class)->dashboard()['response_trend'];

        return [
            'datasets' => [
                [
                    'label' => 'Responses',
                    'data' => array_values($daily),
                ],
            ],
            'labels' => array_keys($daily),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
