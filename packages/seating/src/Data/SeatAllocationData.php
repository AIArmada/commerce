<?php

declare(strict_types=1);

namespace AIArmada\Seating\Data;

use Carbon\CarbonImmutable;

final readonly class SeatAllocationData
{
    public function __construct(
        public string $id,
        public ?string $seatId,
        public ?string $sectionId,
        public ?string $allocatedToType,
        public ?string $allocatedToId,
        public ?string $reference,
        public string $status,
        public ?CarbonImmutable $allocatedAt = null,
        public ?CarbonImmutable $releasedAt = null,
        public ?CarbonImmutable $revokedAt = null,
    ) {}
}
