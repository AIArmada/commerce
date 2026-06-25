<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Models\FeedbackResponse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackAverageRatingWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $query = OwnerUiScope::apply(FeedbackResponse::query(), includeGlobal: false);

        $avg = (clone $query)
            ->where('status', 'submitted')
            ->whereNotNull('score')
            ->avg('score');

        return [
            Stat::make('Average Rating', $avg !== null ? number_format($avg, 2) : 'N/A'),
        ];
    }
}
