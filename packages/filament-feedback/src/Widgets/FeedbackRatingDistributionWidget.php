<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\FilamentFeedback\Support\FeedbackDashboardMemo;
use Filament\Widgets\ChartWidget;

final class FeedbackRatingDistributionWidget extends ChartWidget
{
    protected function getData(): array
    {
        $distribution = app(FeedbackDashboardMemo::class)->dashboard()['rating_distribution'];

        return [
            'datasets' => [
                [
                    'label' => 'Ratings',
                    'data' => array_values($distribution),
                ],
            ],
            'labels' => array_map(fn ($k) => (string) $k, array_keys($distribution)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
