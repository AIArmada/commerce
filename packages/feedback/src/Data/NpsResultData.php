<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final class NpsResultData
{
    public function __construct(
        public readonly ?int $score,
        public readonly int $promoterCount,
        public readonly int $passiveCount,
        public readonly int $detractorCount,
        public readonly int $responseCount,
        public readonly float $promoterPercentage,
        public readonly float $passivePercentage,
        public readonly float $detractorPercentage,
    ) {}
}
