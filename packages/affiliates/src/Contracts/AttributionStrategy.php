<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\AttributionTouch;

interface AttributionStrategy
{
    public function key(): string;

    public function label(): string;

    /**
     * @param  list<AttributionTouch>  $touches
     * @return array<string, float>
     */
    public function distribute(array $touches): array;
}
