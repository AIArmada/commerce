<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final readonly class CsatResultData
{
    public function __construct(
        public ?float $score,
        public int $satisfiedCount,
        public int $neutralCount,
        public int $unsatisfiedCount,
        public int $responseCount,
        public ?float $average,
        public array $distribution,
    ) {}
}
