<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Api;

use Carbon\CarbonImmutable;

interface CartAbandonmentMarker
{
    public function execute(string $snapshotId, CarbonImmutable $cutoff): bool;
}
