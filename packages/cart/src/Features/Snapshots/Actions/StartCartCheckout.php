<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Actions;

use AIArmada\Cart\Events\CartCheckoutStarted;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use Carbon\CarbonImmutable;

final readonly class StartCartCheckout
{
    public function execute(CartSnapshot $snapshot): void
    {
        $started = $snapshot->getConnection()->transaction(function () use ($snapshot): ?CartSnapshot {
            $cart = CartSnapshot::query()->forOwner(includeGlobal: false)
                ->whereKey($snapshot->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $now = CarbonImmutable::now();
            $first = $cart->checkout_started_at === null;
            $cart->last_activity_at = $now;

            if ($first) {
                $cart->checkout_started_at = $now;
            }

            $cart->save();

            return $first ? $cart : null;
        });

        if ($started !== null) {
            event(CartCheckoutStarted::fromSnapshot($started->toSnapshotData()));
        }
    }
}
