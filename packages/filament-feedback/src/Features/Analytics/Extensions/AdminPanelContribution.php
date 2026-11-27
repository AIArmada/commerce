<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Analytics\Extensions;

use AIArmada\FilamentFeedback\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Pages\FeedbackDashboard;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackOverviewWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackResponseTrendWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackAverageRatingWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackNpsWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackCsatWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackRatingDistributionWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackLatestCommentsWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackCompletionRateWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackTestimonialsPendingWidget;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return [];
    }

    public function widgets(): array
    {
        return [
            FeedbackOverviewWidget::class,
            FeedbackResponseTrendWidget::class,
            FeedbackAverageRatingWidget::class,
            FeedbackNpsWidget::class,
            FeedbackCsatWidget::class,
            FeedbackRatingDistributionWidget::class,
            FeedbackLatestCommentsWidget::class,
            FeedbackCompletionRateWidget::class,
            FeedbackTestimonialsPendingWidget::class,
        ];
    }

    public function pages(): array
    {
        return [FeedbackDashboard::class];
    }
}
