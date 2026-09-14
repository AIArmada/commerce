<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\FilamentFeedback\Support\FeedbackDashboardMemo;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackCompletionRateWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $completionRate = app(FeedbackDashboardMemo::class)->dashboard()['completion_rate'];

        return [
            Stat::make('Completion Rate', number_format((float) $completionRate, 1) . '%'),
        ];
    }
}
