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
use Illuminate\Support\Facades\DB;

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
        $totals = DB::query()
            ->selectSub((clone $followQuery)->where('status', 'active')->selectRaw('COUNT(*)'), 'follows')
            ->selectSub((clone $bookmarkQuery)->where('status', 'active')->selectRaw('COUNT(*)'), 'bookmarks')
            ->selectSub((clone $responseQuery)->where('status', 'active')->selectRaw('COUNT(*)'), 'responses')
            ->selectSub((clone $subscriptionQuery)->where('status', 'active')->selectRaw('COUNT(*)'), 'subscriptions')
            ->selectSub((clone $reminderQuery)->whereIn('status', ['pending', 'scheduled'])->selectRaw('COUNT(*)'), 'reminders')
            ->first();

        return [
            Stat::make('Active Follows', (int) ($totals->follows ?? 0))
                ->description('Total active follows')
                ->descriptionIcon('heroicon-o-heart')
                ->color('success'),
            Stat::make('Active Bookmarks', (int) ($totals->bookmarks ?? 0))
                ->description('Total active bookmarks')
                ->descriptionIcon('heroicon-o-bookmark')
                ->color('info'),
            Stat::make('Active Responses', (int) ($totals->responses ?? 0))
                ->description('Total active responses')
                ->descriptionIcon('heroicon-o-hand-thumb-up')
                ->color('warning'),
            Stat::make('Active Subscriptions', (int) ($totals->subscriptions ?? 0))
                ->description('Total active subscriptions')
                ->descriptionIcon('heroicon-o-bell')
                ->color('gray'),
            Stat::make('Due Reminders', (int) ($totals->reminders ?? 0))
                ->description('Reminders waiting to be sent')
                ->descriptionIcon('heroicon-o-clock')
                ->color('danger'),
        ];
    }
}
