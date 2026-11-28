<?php

declare(strict_types=1);

namespace AIArmada\Seating\Data;

final readonly class SeatSelectionData
{
    public function __construct(
        public string $id,
        public string $sectionId,
        public string $status,
    ) {}
}
