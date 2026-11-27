<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Api;

use Carbon\CarbonImmutable;

interface AbandonmentSweep
{
    /**
     * Enumerate snapshot owner tuples from explicit global context.
     *
     * @return list<array{owner_type: ?string, owner_id: string|int|null}>
     */
    public function owners(): array;

    public function count(CarbonImmutable $cutoff): int;

    public function execute(CarbonImmutable $cutoff): int;
}
