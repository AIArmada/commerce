<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventRefundMatchData
{
    public function __construct(
        public ?string $refundId,
        public int $matchedCount,
        public bool $pending,
        public ?string $ownerType = null,
        public string | int | null $ownerId = null,
    ) {}
}
