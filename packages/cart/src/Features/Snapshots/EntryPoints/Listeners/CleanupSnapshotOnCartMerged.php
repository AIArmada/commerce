<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\EntryPoints\Listeners;

use AIArmada\Cart\Features\Snapshots\Actions\ReconcileMergedCartSnapshot;
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
