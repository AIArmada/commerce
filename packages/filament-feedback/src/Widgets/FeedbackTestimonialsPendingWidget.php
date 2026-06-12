<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackTestimonialsPendingWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $owner = OwnerContext::resolve();
        $query = FeedbackTestimonial::query();

        if ($owner !== null) {
            $query = OwnerQuery::applyToEloquentBuilder($query, $owner, false);
        }

        $pending = (clone $query)->where('status', 'pending')->count();
        $approved = (clone $query)->where('status', 'approved')->count();
        $published = (clone $query)->where('status', 'published')->count();

        return [
            Stat::make('Pending Testimonials', $pending),
            Stat::make('Approved', $approved),
            Stat::make('Published', $published),
        ];
    }
}
