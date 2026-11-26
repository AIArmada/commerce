<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

final readonly class PayoutAllocation
{
    /** @param list<string> $conversionIds */
    public function __construct(
        public array $conversionIds,
        public int $amountMinor,
    ) {}
}
