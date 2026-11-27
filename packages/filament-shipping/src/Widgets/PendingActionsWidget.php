<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\Shipping\Features\Reporting\Api\DashboardStats;
use AIArmada\Shipping\Features\Shipments\States\ExceptionStatus;
use AIArmada\Shipping\Features\Shipments\States\Pending;
use AIArmada\Shipping\Features\Shipments\States\ShipmentStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingActionsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $counts = app(DashboardStats::class)->pendingActions();

        return [
            Stat::make('Pending Shipments', $counts['pending'])
                ->description('Ready to ship')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('warning')
                ->url(ShipmentResource::getUrl('index', [
                    'tableFilters[status][value]' => ShipmentStatus::normalize(Pending::class),
                ])),

            Stat::make('Exceptions', $counts['exceptions'])
                ->description('Need attention')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->url(ShipmentResource::getUrl('index', [
                    'tableFilters[status][value]' => ShipmentStatus::normalize(ExceptionStatus::class),
                ])),

            Stat::make('Pending Returns', $counts['pendingReturns'])
                ->description('Awaiting approval')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('info')
                ->url(ReturnAuthorizationResource::getUrl('index', [
                    'tableFilters[status][value]' => 'pending',
                ])),

            Stat::make('Approved Returns', $counts['approvedReturns'])
                ->description('Awaiting shipment')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->url(ReturnAuthorizationResource::getUrl('index', [
                    'tableFilters[status][value]' => 'approved',
                ])),
        ];
    }
}
