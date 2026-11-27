<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Actions;

use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use Carbon\CarbonImmutable;

final readonly class TouchCartActivity
{
    public function execute(CartSnapshot $snapshot): void
    {
        CartSnapshot::query()->forOwner(includeGlobal: false)
            ->whereKey($snapshot->getKey())
            ->update(['last_activity_at' => CarbonImmutable::now()]);
    }
}
