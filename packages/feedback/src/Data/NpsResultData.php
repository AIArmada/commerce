<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final readonly class NpsResultData
{
    public function __construct(
        public ?int $score,
        public int $promoterCount,
        public int $passiveCount,
        public int $detractorCount,
        public int $responseCount,
        public float $promoterPercentage,
        public float $passivePercentage,
        public float $detractorPercentage,
    ) {}
}
