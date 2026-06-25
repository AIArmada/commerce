<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
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
        $followQuery = OwnerUiScope::apply(Follow::query(), includeGlobal: false);
        $bookmarkQuery = OwnerUiScope::apply(Bookmark::query(), includeGlobal: false);
        $responseQuery = OwnerUiScope::apply(Response::query(), includeGlobal: false);
        $subscriptionQuery = OwnerUiScope::apply(Subscription::query(), includeGlobal: false);
        $reminderQuery = OwnerUiScope::apply(Reminder::query(), includeGlobal: false);

        return [
            Stat::make('Active Follows', (clone $followQuery)->where('status', 'active')->count())
                ->description('Total active follows')
                ->descriptionIcon('heroicon-o-heart')
                ->color('success'),
            Stat::make('Active Bookmarks', (clone $bookmarkQuery)->where('status', 'active')->count())
                ->description('Total active bookmarks')
                ->descriptionIcon('heroicon-o-bookmark')
                ->color('info'),
            Stat::make('Active Responses', (clone $responseQuery)->where('status', 'active')->count())
                ->description('Total active responses')
                ->descriptionIcon('heroicon-o-hand-thumb-up')
                ->color('warning'),
            Stat::make('Active Subscriptions', (clone $subscriptionQuery)->where('status', 'active')->count())
                ->description('Total active subscriptions')
                ->descriptionIcon('heroicon-o-bell')
                ->color('gray'),
            Stat::make('Due Reminders', (clone $reminderQuery)->whereIn('status', ['pending', 'scheduled'])->count())
                ->description('Reminders waiting to be sent')
                ->descriptionIcon('heroicon-o-clock')
                ->color('danger'),
        ];
    }
}
