<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\Shipment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class ShippingStatsAggregator
{
    public function getPendingCount(): int
    {
        return $this->shipmentQuery()
            ->where('status', ShipmentStatus::Pending)
            ->count();
    }

    public function getInTransitCount(): int
    {
        return $this->shipmentQuery()
            ->whereIn('status', [
                ShipmentStatus::Shipped,
                ShipmentStatus::InTransit,
                ShipmentStatus::OutForDelivery,
            ])
            ->count();
    }

    public function getDeliveredTodayCount(): int
    {
        return $this->shipmentQuery()
            ->where('status', ShipmentStatus::Delivered)
            ->whereDate('delivered_at', CarbonImmutable::today())
            ->count();
    }

    public function getExceptionsCount(): int
    {
        return $this->shipmentQuery()
            ->whereIn('status', [
                ShipmentStatus::Exception,
                ShipmentStatus::DeliveryFailed,
            ])
            ->count();
    }

    public function getPendingReturnsCount(): int
    {
        $query = ReturnAuthorization::query()->withoutGlobalScope(OwnerScope::class);

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return 0;
            }

            $query->forOwner($owner, includeGlobal: (bool) config('shipping.features.owner.include_global', false));
        }

        return $query
            ->where('status', 'pending')
            ->count();
    }

    /**
     * @return array{total: int, pending: int, inTransit: int, deliveredToday: int, exceptions: int, pendingReturns: int}
     */
    public function getAllStats(): array
    {
        return [
            'total' => $this->getPendingCount() + $this->getInTransitCount() + $this->getDeliveredTodayCount() + $this->getExceptionsCount(),
            'pending' => $this->getPendingCount(),
            'inTransit' => $this->getInTransitCount(),
            'deliveredToday' => $this->getDeliveredTodayCount(),
            'exceptions' => $this->getExceptionsCount(),
            'pendingReturns' => $this->getPendingReturnsCount(),
        ];
    }

    private function shipmentQuery(): Builder
    {
        $query = Shipment::query()->withoutGlobalScope(OwnerScope::class);

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return $query->whereRaw('0 = 1');
            }

            $query->forOwner($owner, includeGlobal: (bool) config('shipping.features.owner.include_global', false));
        }

        return $query;
    }
}
