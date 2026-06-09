<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Data;

use Spatie\LaravelData\Data;

final class TierPriceResultData extends Data
{
    public function __construct(
        public int $price,
        public string $tier,
    ) {}
}
