<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackResponse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $owner = OwnerContext::resolve();
        $includeGlobal = false;

        $formQuery = FeedbackForm::query();
        $responseQuery = FeedbackResponse::query();

        if ($owner !== null) {
            $formQuery = OwnerQuery::applyToEloquentBuilder($formQuery, $owner, $includeGlobal);
            $responseQuery = OwnerQuery::applyToEloquentBuilder($responseQuery, $owner, $includeGlobal);
        }

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
