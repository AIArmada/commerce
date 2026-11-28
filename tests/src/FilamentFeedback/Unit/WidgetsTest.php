<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentFeedback\FilamentFeedbackTestCase;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Pages\FeedbackDashboard;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackAverageRatingWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackCompletionRateWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackCsatWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackLatestCommentsWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackNpsWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackOverviewWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackRatingDistributionWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackResponseTrendWidget;
use AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets\FeedbackTestimonialsPendingWidget;

uses(FilamentFeedbackTestCase::class);

it('composes the dashboard from all nine analytics widgets', function (): void {
    $widgetClasses = (new FeedbackDashboard)->getWidgets();

    expect($widgetClasses)->toHaveCount(9)
        ->toEqual([
            FeedbackOverviewWidget::class,
            FeedbackResponseTrendWidget::class,
            FeedbackAverageRatingWidget::class,
            FeedbackNpsWidget::class,
            FeedbackCsatWidget::class,
            FeedbackRatingDistributionWidget::class,
            FeedbackLatestCommentsWidget::class,
            FeedbackCompletionRateWidget::class,
            FeedbackTestimonialsPendingWidget::class,
        ]);
});

it('loads every widget through the shared analytics dashboard', function (): void {
    $invoke = static function (object $widget, string $method): mixed {
        $reflection = new ReflectionMethod($widget, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($widget);
    };

    expect($invoke(new FeedbackOverviewWidget, 'getStats'))->toHaveCount(4)
        ->and($invoke(new FeedbackResponseTrendWidget, 'getData'))->toHaveKeys(['datasets', 'labels'])
        ->and($invoke(new FeedbackAverageRatingWidget, 'getStats'))->toHaveCount(1)
        ->and($invoke(new FeedbackNpsWidget, 'getStats'))->toHaveCount(1)
        ->and($invoke(new FeedbackCsatWidget, 'getStats'))->toHaveCount(1)
        ->and($invoke(new FeedbackRatingDistributionWidget, 'getData'))->toHaveKeys(['datasets', 'labels'])
        ->and((new FeedbackLatestCommentsWidget)->getComments())->toBe([])
        ->and($invoke(new FeedbackCompletionRateWidget, 'getStats'))->toHaveCount(1)
        ->and($invoke(new FeedbackTestimonialsPendingWidget, 'getStats'))->toHaveCount(3);
});
