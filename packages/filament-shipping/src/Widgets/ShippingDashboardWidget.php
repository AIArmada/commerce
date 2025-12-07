<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\Shipment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShippingDashboardWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            Stat::make('Pending Shipments', $this->getPendingCount())
                ->description('Awaiting shipping')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('In Transit', $this->getInTransitCount())
                ->description('Currently shipping')
                ->icon('heroicon-o-truck')
                ->color('info'),

            Stat::make('Delivered Today', $this->getDeliveredTodayCount())
                ->description('Successful deliveries')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Exceptions', $this->getExceptionsCount())
                ->description('Need attention')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make('Pending Returns', $this->getPendingReturnsCount())
                ->description('Awaiting approval')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning'),
        ];
    }

    protected function getPendingCount(): int
    {
        return Shipment::query()
            ->whereIn('status', [ShipmentStatus::Draft, ShipmentStatus::Pending])
            ->count();
    }

    protected function getInTransitCount(): int
    {
        return Shipment::query()
            ->whereIn('status', [
                ShipmentStatus::Shipped,
                ShipmentStatus::InTransit,
                ShipmentStatus::OutForDelivery,
            ])
            ->count();
    }

    protected function getDeliveredTodayCount(): int
    {
        return Shipment::query()
            ->where('status', ShipmentStatus::Delivered)
            ->whereDate('delivered_at', today())
            ->count();
    }

    protected function getExceptionsCount(): int
    {
        return Shipment::query()
            ->whereIn('status', [
                ShipmentStatus::Exception,
                ShipmentStatus::DeliveryFailed,
            ])
            ->count();
    }

    protected function getPendingReturnsCount(): int
    {
        return ReturnAuthorization::query()
            ->where('status', 'pending')
            ->count();
    }
}
