<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Queries;

use AIArmada\Inventory\Models\InventoryReservation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class ExpiredReservations
{
    /** @return Builder<InventoryReservation> */
    public function query(CarbonImmutable $now): Builder
    {
        $cutoff = $now->subMinutes(max(0, (int) config('inventory.cleanup.keep_expired_for_minutes', 0)));

        return InventoryOwnerScope::applyToLocationQuery(InventoryReservation::query())
            ->where(function (Builder $query) use ($now, $cutoff): void {
                $query
                    ->where(function (Builder $reserved) use ($now): void {
                        $reserved
                            ->where('status', InventoryReservation::STATE_RESERVED)
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', $now);
                    })
                    ->orWhere(function (Builder $terminal) use ($cutoff): void {
                        $terminal
                            ->whereIn('status', [
                                InventoryReservation::STATE_COMMITTED,
                                InventoryReservation::STATE_RELEASED,
                                InventoryReservation::STATE_EXPIRED,
                            ])
                            ->where('updated_at', '<=', $cutoff);
                    });
            });
    }
}
