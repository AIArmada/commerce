<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Pages;

use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackAverageRatingWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackCompletionRateWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackCsatWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackLatestCommentsWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackNpsWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackOverviewWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackRatingDistributionWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackResponseTrendWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackTestimonialsPendingWidget;
use BackedEnum;
use Filament\Pages\Dashboard;

final class FeedbackDashboard extends Dashboard
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-feedback.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-feedback.pages.navigation_sort.dashboard');

        return is_numeric($sort) ? (int) $sort : null;
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
