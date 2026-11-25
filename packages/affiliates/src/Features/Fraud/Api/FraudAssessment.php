<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Api;

final readonly class FraudAssessment
{
    /** @param list<FraudSignalRecord> $signals */
    public function __construct(
        public bool $allowed,
        public int $score,
        public array $signals,
    ) {}
}
