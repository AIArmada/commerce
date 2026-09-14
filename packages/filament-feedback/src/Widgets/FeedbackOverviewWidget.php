<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\FilamentFeedback\Support\FeedbackDashboardMemo;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $overview = app(FeedbackDashboardMemo::class)->dashboard()['overview'];

        return [
            Stat::make('Total Forms', $overview['total_forms']),
            Stat::make('Published Forms', $overview['published_forms']),
            Stat::make('Total Responses', $overview['total_responses']),
            Stat::make('Submitted Responses', $overview['submitted_responses']),
        ];
    }
}
