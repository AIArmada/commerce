<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Feedback\Models\FeedbackAnswer;
use Filament\Widgets\ChartWidget;

final class FeedbackRatingDistributionWidget extends ChartWidget
{
    protected function getData(): array
    {
        $owner = OwnerContext::resolve();
        $query = FeedbackAnswer::query();

        if ($owner !== null) {
            $query = OwnerQuery::applyToEloquentBuilder($query, $owner, false);
        }

        $distribution = (clone $query)
            ->whereNotNull('number_value')
            ->selectRaw('number_value, COUNT(*) as count')
            ->groupBy('number_value')
            ->orderBy('number_value')
            ->pluck('count', 'number_value')
            ->toArray();

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
