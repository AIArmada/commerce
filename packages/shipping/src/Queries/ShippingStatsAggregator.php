<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\States\Delivered;
use AIArmada\Shipping\States\DeliveryFailed;
use AIArmada\Shipping\States\ExceptionStatus;
use AIArmada\Shipping\States\InTransit;
use AIArmada\Shipping\States\OutForDelivery;
use AIArmada\Shipping\States\Pending;
use AIArmada\Shipping\States\ShipmentStatus;
use AIArmada\Shipping\States\Shipped;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class ShippingStatsAggregator
{
    /**
     * @return array{total: int, pending: int, inTransit: int, deliveredToday: int, exceptions: int, pendingReturns: int}
     */
    public function getAllStats(): array
    {
        $pendingStatus = ShipmentStatus::normalize(Pending::class);
        $inTransitStatuses = [
            ShipmentStatus::normalize(Shipped::class),
            ShipmentStatus::normalize(InTransit::class),
            ShipmentStatus::normalize(OutForDelivery::class),
        ];
        $exceptionStatuses = [
            ShipmentStatus::normalize(ExceptionStatus::class),
            ShipmentStatus::normalize(DeliveryFailed::class),
        ];

        $counts = $this->shipmentQuery()
            ->whereIn('status', [$pendingStatus, ...$inTransitStatuses, ...$exceptionStatuses])
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->toBase()
            ->pluck('aggregate', 'status');

        $pending = (int) $counts->get($pendingStatus, 0);
        $inTransit = array_sum(array_map(static fn (string $status): int => (int) $counts->get($status, 0), $inTransitStatuses));
        $exceptions = array_sum(array_map(static fn (string $status): int => (int) $counts->get($status, 0), $exceptionStatuses));
        $deliveredToday = $this->shipmentQuery()
            ->where('status', ShipmentStatus::normalize(Delivered::class))
            ->whereDate('delivered_at', CarbonImmutable::today())
            ->count();

        return [
            'total' => $pending + $inTransit + $deliveredToday + $exceptions,
            'pending' => $pending,
            'inTransit' => $inTransit,
            'deliveredToday' => $deliveredToday,
            'exceptions' => $exceptions,
            'pendingReturns' => $this->getPendingReturnsCount(),
        ];
    }

    private function getPendingReturnsCount(): int
    {
        $query = ReturnAuthorization::query()->withoutGlobalScope(OwnerScope::class);

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return 0;
            }

            $query->forOwner($owner, includeGlobal: ! OwnerScopeOverride::suppressIncludeGlobal() && (bool) config('shipping.features.owner.include_global', false));
        }

        return $query
            ->where('status', 'pending')
            ->count();
    }

    /**
     * @return Builder<Shipment>
     */
    private function shipmentQuery(): Builder
    {
        $query = Shipment::query()->withoutGlobalScope(OwnerScope::class);

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return $query->whereRaw('0 = 1');
            }

            $query->forOwner($owner, includeGlobal: ! OwnerScopeOverride::suppressIncludeGlobal() && (bool) config('shipping.features.owner.include_global', false));
        }

        return $query;
    }
}
