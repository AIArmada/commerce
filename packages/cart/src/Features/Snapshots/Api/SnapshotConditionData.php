<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Api;

final readonly class SnapshotConditionData
{
    public function __construct(
        public string $name,
        public ?string $itemId,
        public bool $cartLevel,
        public mixed $conditionId,
    ) {}
}
