<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use Illuminate\Support\Collection;

interface AttributionStrategy
{
    public function key(): string;

    public function label(): string;

    public function distribute(Collection $touches): array;
}
