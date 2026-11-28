<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Data;

use Carbon\CarbonImmutable;

final readonly class PassTransferData
{
    public function __construct(
        public PassData $pass,
        public PassHolderData $previousHolder,
        public PassHolderData $newHolder,
        public ?string $reason = null,
        public CarbonImmutable $transferredAt = new CarbonImmutable,
        public bool $bulk = false,
    ) {}
}
