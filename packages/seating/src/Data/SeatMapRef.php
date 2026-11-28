<?php

declare(strict_types=1);

namespace AIArmada\Seating\Data;

final readonly class SeatMapRef
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public int $version,
    ) {}
}
