<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\Feedback\Analytics\FeedbackAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackAverageRatingWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $avg = app(FeedbackAnalyticsService::class)->dashboard()['average_rating'];

        return [
            Stat::make('Average Rating', $avg !== null ? number_format((float) $avg, 2) : 'N/A'),
        ];
    }
}
