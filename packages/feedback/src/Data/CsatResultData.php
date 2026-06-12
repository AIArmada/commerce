<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final class CsatResultData
{
    public function __construct(
        public readonly ?float $score,
        public readonly int $satisfiedCount,
        public readonly int $neutralCount,
        public readonly int $unsatisfiedCount,
        public readonly int $responseCount,
        public readonly ?float $average,
        public readonly array $distribution,
    ) {}
}
