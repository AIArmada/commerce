<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Api;

use AIArmada\Affiliates\Enums\FraudSeverity;
use Carbon\CarbonImmutable;

final readonly class FraudSignalSnapshot
{
    public function __construct(
        public FraudSignalReference $reference,
        public string $ruleCode,
        public FraudSeverity $severity,
        public int $riskPoints,
        public ?CarbonImmutable $detectedAt,
    ) {}
}
