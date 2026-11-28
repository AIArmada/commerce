<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Widgets;

use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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
                $model = new CommunicationDelivery;
                $counts = OwnerUiScope::apply(CommunicationDelivery::query(), includeGlobal: false)
                    ->select('status', $model->getConnection()->raw('COUNT(*) as aggregate'))
                    ->groupBy('status')
                    ->pluck('aggregate', 'status');

                $totals = [
                    'pending' => 0,
                    'sent' => 0,
                    'delivered' => 0,
                    'failed' => 0,
                    'suppressed' => 0,
                ];

                foreach ($counts as $status => $count) {
                    $bucket = match ($status) {
                        DeliveryStatus::Pending->value,
                        DeliveryStatus::Scheduled->value,
                        DeliveryStatus::Queued->value,
                        DeliveryStatus::Sending->value => 'pending',
                        DeliveryStatus::Sent->value,
                        DeliveryStatus::Accepted->value,
                        DeliveryStatus::Received->value => 'sent',
                        DeliveryStatus::Delivered->value,
                        DeliveryStatus::Opened->value,
                        DeliveryStatus::Read->value,
                        DeliveryStatus::Clicked->value,
                        DeliveryStatus::Replied->value => 'delivered',
                        DeliveryStatus::Failed->value,
                        DeliveryStatus::Bounced->value,
                        DeliveryStatus::Complained->value,
                        DeliveryStatus::Expired->value => 'failed',
                        DeliveryStatus::Suppressed->value,
                        DeliveryStatus::Unsubscribed->value,
                        DeliveryStatus::Cancelled->value => 'suppressed',
                        default => null,
                    };

                    if ($bucket !== null) {
                        $totals[$bucket] += (int) $count;
                    }
                }

                return $totals;
            }
        );
    }

    protected function getColumns(): int
    {
        return 5;
    }
}
