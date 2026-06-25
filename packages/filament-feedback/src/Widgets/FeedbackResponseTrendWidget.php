<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Models\FeedbackResponse;
use Filament\Widgets\ChartWidget;

final class FeedbackResponseTrendWidget extends ChartWidget
{
    protected function getData(): array
    {
        $query = OwnerUiScope::apply(FeedbackResponse::query(), includeGlobal: false);

        $daily = (clone $query)
            ->where('status', 'submitted')
            ->selectRaw('DATE(submitted_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->limit(30)
            ->pluck('count', 'date')
            ->toArray();

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
