<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Widgets;

use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Models\Response;
use AIArmada\Engagement\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class EngagementOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Active Follows', Follow::query()->where('status', 'active')->count())
                ->description('Total active follows')
                ->descriptionIcon('heroicon-o-heart')
                ->color('success'),
            Stat::make('Active Bookmarks', Bookmark::query()->where('status', 'active')->count())
                ->description('Total active bookmarks')
                ->descriptionIcon('heroicon-o-bookmark')
                ->color('info'),
            Stat::make('Active Responses', Response::query()->where('status', 'active')->count())
                ->description('Total active responses')
                ->descriptionIcon('heroicon-o-hand-thumb-up')
                ->color('warning'),
            Stat::make('Active Subscriptions', Subscription::query()->where('status', 'active')->count())
                ->description('Total active subscriptions')
                ->descriptionIcon('heroicon-o-bell')
                ->color('gray'),
            Stat::make('Due Reminders', Reminder::query()->whereIn('status', ['pending', 'scheduled'])->count())
                ->description('Reminders waiting to be sent')
                ->descriptionIcon('heroicon-o-clock')
                ->color('danger'),
        ];
    }
}
