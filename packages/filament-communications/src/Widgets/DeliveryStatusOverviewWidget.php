<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\CommunicationDelivery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

final class DeliveryStatusOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $totals = $this->cachedTotals();

        return [
            Stat::make('Pending', $totals['pending'])
                ->description('Awaiting delivery')
                ->descriptionIcon('heroicon-o-clock')
                ->color('gray'),
            Stat::make('Sent', $totals['sent'])
                ->description('Transmitted to provider')
                ->descriptionIcon('heroicon-o-paper-airplane')
                ->color('info'),
            Stat::make('Delivered', $totals['delivered'])
                ->description('Confirmed delivered')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Failed', $totals['failed'])
                ->description('Delivery failed')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),
            Stat::make('Suppressed', $totals['suppressed'])
                ->description('Suppressed or stopped')
                ->descriptionIcon('heroicon-o-no-symbol')
                ->color('warning'),
        ];
    }

    /**
     * Every DeliveryStatus case belongs to exactly one bucket so the stats
     * reconcile with the delivery total.
     *
     * @return array{pending: int, sent: int, delivered: int, failed: int, suppressed: int}
     */
    private function cachedTotals(): array
    {
        return OwnerCache::remember(
            OwnerUiScope::resolveOwner(CommunicationDelivery::class),
            'filament-communications.delivery-overview.totals',
            60,
            function (): array {
                $baseQuery = OwnerUiScope::apply(CommunicationDelivery::query(), includeGlobal: false);
                $row = DB::query()
                    ->selectSub((clone $baseQuery)->whereIn('status', [
                        DeliveryStatus::Pending,
                        DeliveryStatus::Scheduled,
                        DeliveryStatus::Queued,
                        DeliveryStatus::Sending,
                    ])->selectRaw('COUNT(*)'), 'pending')
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
                        DeliveryStatus::Replied,
                    ])->selectRaw('COUNT(*)'), 'delivered')
                    ->selectSub((clone $baseQuery)->whereIn('status', [
                        DeliveryStatus::Failed,
                        DeliveryStatus::Bounced,
                        DeliveryStatus::Complained,
                        DeliveryStatus::Expired,
                    ])->selectRaw('COUNT(*)'), 'failed')
                    ->selectSub((clone $baseQuery)->whereIn('status', [
                        DeliveryStatus::Suppressed,
                        DeliveryStatus::Unsubscribed,
                        DeliveryStatus::Cancelled,
                    ])->selectRaw('COUNT(*)'), 'suppressed')
                    ->first();

                return [
                    'pending' => (int) ($row->pending ?? 0),
                    'sent' => (int) ($row->sent ?? 0),
                    'delivered' => (int) ($row->delivered ?? 0),
                    'failed' => (int) ($row->failed ?? 0),
                    'suppressed' => (int) ($row->suppressed ?? 0),
                ];
            }
        );
    }

    protected function getColumns(): int
    {
        return 5;
    }
}
