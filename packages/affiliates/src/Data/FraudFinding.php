<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\Affiliates\Features\Fraud\Api\FraudSeverity;

final readonly class FraudFinding
{
    /** @param array<string, mixed> $evidence */
    public function __construct(
        public string $ruleCode,
        public int $riskPoints,
        public FraudSeverity $severity,
        public string $description,
        public array $evidence,
    ) {}
}
