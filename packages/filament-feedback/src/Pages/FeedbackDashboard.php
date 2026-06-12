<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Pages;

use AIArmada\FilamentFeedback\Widgets\FeedbackAverageRatingWidget;
use AIArmada\FilamentFeedback\Widgets\FeedbackCompletionRateWidget;
use AIArmada\FilamentFeedback\Widgets\FeedbackCsatWidget;
use AIArmada\FilamentFeedback\Widgets\FeedbackLatestCommentsWidget;
use AIArmada\FilamentFeedback\Widgets\FeedbackNpsWidget;
use AIArmada\FilamentFeedback\Widgets\FeedbackOverviewWidget;
use AIArmada\FilamentFeedback\Widgets\FeedbackRatingDistributionWidget;
use AIArmada\FilamentFeedback\Widgets\FeedbackResponseTrendWidget;
use AIArmada\FilamentFeedback\Widgets\FeedbackTestimonialsPendingWidget;
use BackedEnum;
use Filament\Pages\Dashboard;

final class FeedbackDashboard extends Dashboard
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-feedback.navigation.group', 'Feedback');
    }

    public function getWidgets(): array
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
}
