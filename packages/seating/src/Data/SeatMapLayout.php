<?php

declare(strict_types=1);

namespace AIArmada\Seating\Data;

final readonly class SeatMapLayout
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public int $version,
        /** @var array<int, array> */
        public array $sections = [],
        /** @var array{rows: int, cols: int} */
        public array $bounds = ['rows' => 0, 'cols' => 0],
    ) {}
}
