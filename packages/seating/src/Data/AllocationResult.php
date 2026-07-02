<?php

declare(strict_types=1);

namespace AIArmada\Seating\Data;

use Spatie\LaravelData\Data;

class AllocationResult extends Data
{
    public function __construct(
        public string $seatId,
        public string $sectionCode,
        public string $rowLabel,
        public string $seatLabel,
        public ?string $category = null,
        public ?string $holdId = null,
    ) {}
}
