<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentJnt\Support\JntStatsAggregator;
use AIArmada\Jnt\Models\JntOrder;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class JntStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = Cache::remember(
            $this->statsCacheKey(),
            CarbonImmutable::now()->addSeconds(30),
            fn (): array => $this->calculateOrderStats()
        );

        $totalOrders = (int) ($stats['total'] ?? 0);
        $deliveredCount = (int) ($stats['delivered'] ?? 0);
        $inTransitCount = (int) ($stats['in_transit'] ?? 0);
        $problemCount = (int) ($stats['problems'] ?? 0);
        $pendingCount = (int) ($stats['pending'] ?? 0);
        $returningCount = (int) ($stats['returns'] ?? 0);

        $deliveryRate = $totalOrders > 0
            ? round(($deliveredCount / $totalOrders) * 100, 1)
            : 0;

        return [
            Stat::make('Total Orders', $totalOrders)
                ->description('All shipping orders')
                ->descriptionIcon(Heroicon::RectangleStack)
                ->color('primary'),

            Stat::make('Delivered', $deliveredCount)
                ->description($deliveryRate . '% delivery rate')
                ->descriptionIcon(Heroicon::CheckCircle)
                ->color('success'),

            Stat::make('In Transit', $inTransitCount)
                ->description('On the way')
                ->descriptionIcon(Heroicon::Truck)
                ->color('info'),

            Stat::make('Pending', $pendingCount)
                ->description('Awaiting pickup')
                ->descriptionIcon(Heroicon::Clock)
                ->color('warning'),

            Stat::make('Returns', $returningCount)
                ->description('Being returned')
                ->descriptionIcon(Heroicon::ArrowUturnLeft)
                ->color($returningCount > 0 ? 'purple' : 'gray'),

            Stat::make('Problems', $problemCount)
                ->description('Requires attention')
                ->descriptionIcon(Heroicon::ExclamationTriangle)
                ->color($problemCount > 0 ? 'danger' : 'success'),
        ];
    }

    protected function getColumns(): int
    {
        return 6;
    }

    private function statsCacheKey(): string
    {
        $owner = OwnerUiScope::resolveOwner(JntOrder::class);
        $ownerKey = $owner instanceof Model
            ? $owner->getMorphClass() . ':' . (string) $owner->getKey()
            : 'none';

        $includeGlobal = (bool) config('jnt.owner.include_global', false);

        return 'filament-jnt:widget:stats:' . $ownerKey . ':' . ($includeGlobal ? '1' : '0');
    }

    /**
     * @return array{total:int, delivered:int, in_transit:int, problems:int, pending:int, returns:int}
     */
    private function calculateOrderStats(): array
    {
        return JntStatsAggregator::calculateOrderStats();
    }
}
