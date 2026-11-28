<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets;

use AIArmada\FilamentFeedback\Features\Analytics\Queries\FeedbackDashboardMemo;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackNpsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $nps = app(FeedbackDashboardMemo::class)->dashboard()['nps'];

        return [
            Stat::make('NPS', $nps->score !== null ? (string) $nps->score : 'N/A'),
        ];
    }
}
