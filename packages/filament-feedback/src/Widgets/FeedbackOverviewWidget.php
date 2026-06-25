<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackResponse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $formQuery = OwnerUiScope::apply(FeedbackForm::query(), includeGlobal: false);
        $responseQuery = OwnerUiScope::apply(FeedbackResponse::query(), includeGlobal: false);

        $totalForms = (clone $formQuery)->count();
        $publishedForms = (clone $formQuery)->where('status', 'published')->count();
        $totalResponses = (clone $responseQuery)->count();
        $submittedResponses = (clone $responseQuery)->where('status', 'submitted')->count();

        return [
            Stat::make('Total Forms', $totalForms),
            Stat::make('Published Forms', $publishedForms),
            Stat::make('Total Responses', $totalResponses),
            Stat::make('Submitted Responses', $submittedResponses),
        ];
    }
}
