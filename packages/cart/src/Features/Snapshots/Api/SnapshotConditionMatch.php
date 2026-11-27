<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Api;

use AIArmada\Cart\Data\CartSnapshotData;

final readonly class SnapshotConditionMatch
{
    /** @param list<SnapshotConditionData> $conditions */
    public function __construct(
        public CartSnapshotData $snapshot,
        public array $conditions,
    ) {}
}
