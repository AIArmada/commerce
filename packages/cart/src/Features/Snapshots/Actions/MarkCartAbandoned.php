<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Actions;

use AIArmada\Cart\Events\CartAbandoned;
use AIArmada\Cart\Features\Snapshots\Api\CartAbandonmentMarker;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use Carbon\CarbonImmutable;

final readonly class MarkCartAbandoned implements CartAbandonmentMarker
{
    public function execute(string $snapshotId, CarbonImmutable $cutoff): bool
    {
        $abandoned = (new CartSnapshot)->getConnection()->transaction(function () use ($snapshotId, $cutoff): ?CartSnapshot {
            $cart = CartSnapshot::query()->forOwner(includeGlobal: false)
                ->whereKey($snapshotId)
                ->where('items_count', '>', 0)
                ->whereNotNull('checkout_started_at')
                ->whereNull('checkout_abandoned_at')
                ->where(function ($query) use ($cutoff): void {
                    $query->where('last_activity_at', '<', $cutoff)
                        ->orWhere(function ($fallback) use ($cutoff): void {
                            $fallback->whereNull('last_activity_at')
                                ->where('checkout_started_at', '<', $cutoff);
                        });
                })
                ->lockForUpdate()
                ->first();

            if ($cart === null) {
                return null;
            }

            $cart->checkout_abandoned_at = CarbonImmutable::now();
            $cart->save();

            return $cart;
        });

        if ($abandoned === null) {
            return false;
        }

        event(CartAbandoned::fromSnapshot($abandoned->toSnapshotData()));

        return true;
    }
}
