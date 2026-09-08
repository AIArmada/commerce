<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\Feedback\Analytics\FeedbackAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackTestimonialsPendingWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $testimonials = app(FeedbackAnalyticsService::class)->dashboard()['testimonials'];

        return [
            Stat::make('Pending Testimonials', $testimonials['pending']),
            Stat::make('Approved', $testimonials['approved']),
            Stat::make('Published', $testimonials['published']),
        ];
    }
}
