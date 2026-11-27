<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Data;

final readonly class TierPriceResultData
{
    public function __construct(
        public int $price,
        public string $tier,
    ) {}
}
