<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\CommunicationDelivery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

final class DeliveryStatusOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $baseQuery = OwnerUiScope::apply(CommunicationDelivery::query(), includeGlobal: false);
        $totals = DB::query()
            ->selectSub((clone $baseQuery)->where('status', DeliveryStatus::Pending)->selectRaw('COUNT(*)'), 'pending')
            ->selectSub((clone $baseQuery)->whereIn('status', [
                DeliveryStatus::Sent,
                DeliveryStatus::Accepted,
                DeliveryStatus::Received,
            ])->selectRaw('COUNT(*)'), 'sent')
            ->selectSub((clone $baseQuery)->whereIn('status', [
                DeliveryStatus::Delivered,
                DeliveryStatus::Opened,
                DeliveryStatus::Read,
                DeliveryStatus::Clicked,
            ])->selectRaw('COUNT(*)'), 'delivered')
            ->selectSub((clone $baseQuery)->whereIn('status', [
                DeliveryStatus::Failed,
                DeliveryStatus::Bounced,
                DeliveryStatus::Complained,
                DeliveryStatus::Expired,
            ])->selectRaw('COUNT(*)'), 'failed')
            ->first();

        return [
            Stat::make('Pending', (int) ($totals->pending ?? 0))
                ->description('Awaiting delivery')
                ->descriptionIcon('heroicon-o-clock')
                ->color('gray'),
            Stat::make('Sent', (int) ($totals->sent ?? 0))
                ->description('Transmitted to provider')
                ->descriptionIcon('heroicon-o-paper-airplane')
                ->color('info'),
            Stat::make('Delivered', (int) ($totals->delivered ?? 0))
                ->description('Confirmed delivered')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Failed', (int) ($totals->failed ?? 0))
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
