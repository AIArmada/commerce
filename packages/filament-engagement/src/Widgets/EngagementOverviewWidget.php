<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerCache;
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
    private const int CACHE_TTL_SECONDS = 30;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $owner = OwnerUiScope::resolveOwner(Follow::class);
        $totals = OwnerCache::remember(
            $owner,
            'filament-engagement.overview.stats',
            self::CACHE_TTL_SECONDS,
            function (): array {
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
                    'follows' => (int) ($totals->follows ?? 0),
                    'bookmarks' => (int) ($totals->bookmarks ?? 0),
                    'responses' => (int) ($totals->responses ?? 0),
                    'subscriptions' => (int) ($totals->subscriptions ?? 0),
                    'reminders' => (int) ($totals->reminders ?? 0),
                ];
            },
        );

        /** @var array{follows: int, bookmarks: int, responses: int, subscriptions: int, reminders: int} $totals */

        return [
            Stat::make('Active Follows', $totals['follows'])
                ->description('Total active follows')
                ->descriptionIcon('heroicon-o-heart')
                ->color('success'),
            Stat::make('Active Bookmarks', $totals['bookmarks'])
                ->description('Total active bookmarks')
                ->descriptionIcon('heroicon-o-bookmark')
                ->color('info'),
            Stat::make('Active Responses', $totals['responses'])
                ->description('Total active responses')
                ->descriptionIcon('heroicon-o-hand-thumb-up')
                ->color('warning'),
            Stat::make('Active Subscriptions', $totals['subscriptions'])
                ->description('Total active subscriptions')
                ->descriptionIcon('heroicon-o-bell')
                ->color('gray'),
            Stat::make('Due Reminders', $totals['reminders'])
                ->description('Reminders waiting to be sent')
                ->descriptionIcon('heroicon-o-clock')
                ->color('danger'),
        ];
    }
}
