<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Feedback\Models\FeedbackResponse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackCompletionRateWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $owner = OwnerContext::resolve();
        $query = FeedbackResponse::query();

        if ($owner !== null) {
            $query = OwnerQuery::applyToEloquentBuilder($query, $owner, false);
        }

        $total = (clone $query)->count();
        $submitted = (clone $query)->where('status', 'submitted')->count();

        return [
            Stat::make('Completion Rate', $total > 0
                ? number_format(($submitted / $total) * 100, 1) . '%'
                : '0%'),
        ];
    }
}
