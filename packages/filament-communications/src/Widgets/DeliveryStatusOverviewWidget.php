<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\CommunicationDelivery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class DeliveryStatusOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $baseQuery = OwnerUiScope::apply(CommunicationDelivery::query(), includeGlobal: false);

        return [
            Stat::make('Pending', (clone $baseQuery)->where('status', DeliveryStatus::Pending)->count())
                ->description('Awaiting delivery')
                ->descriptionIcon('heroicon-o-clock')
                ->color('gray'),
            Stat::make('Sent', (clone $baseQuery)->whereIn('status', [
                DeliveryStatus::Sent,
                DeliveryStatus::Accepted,
                DeliveryStatus::Received,
            ])->count())
                ->description('Transmitted to provider')
                ->descriptionIcon('heroicon-o-paper-airplane')
                ->color('info'),
            Stat::make('Delivered', (clone $baseQuery)->whereIn('status', [
                DeliveryStatus::Delivered,
                DeliveryStatus::Opened,
                DeliveryStatus::Read,
                DeliveryStatus::Clicked,
            ])->count())
                ->description('Confirmed delivered')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Failed', (clone $baseQuery)->whereIn('status', [
                DeliveryStatus::Failed,
                DeliveryStatus::Bounced,
                DeliveryStatus::Complained,
                DeliveryStatus::Expired,
            ])->count())
                ->description('Delivery failed')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
