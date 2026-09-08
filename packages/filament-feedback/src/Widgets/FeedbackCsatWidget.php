<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\Feedback\Analytics\FeedbackAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackCsatWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $csat = app(FeedbackAnalyticsService::class)->dashboard()['csat'];

        return [
            Stat::make('CSAT', $csat->score !== null
                ? number_format($csat->score, 1) . '%'
                : 'N/A'),
        ];
    }
}
