<?php

declare(strict_types=1);

namespace AIArmada\Cart\Snapshots;

use AIArmada\Cart\Actions\ReconcileMergedCartSnapshot;
use AIArmada\Cart\Events\CartMerged;
use Throwable;

final class CleanupSnapshotOnCartMerged
{
    public function handle(CartMerged $event): void
    {
        try {
            app(ReconcileMergedCartSnapshot::class)->execute($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
