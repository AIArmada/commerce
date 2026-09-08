<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\Feedback\Analytics\FeedbackAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackNpsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $nps = app(FeedbackAnalyticsService::class)->dashboard()['nps'];

        return [
            Stat::make('NPS', $nps->score !== null ? (string) $nps->score : 'N/A'),
        ];
    }
}
