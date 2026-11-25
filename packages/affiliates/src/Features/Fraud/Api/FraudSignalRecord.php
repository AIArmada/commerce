<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Api;

final readonly class FraudSignalRecord
{
    public function __construct(
        public string $id,
        public string $affiliateId,
        public ?string $conversionId,
        public string $ruleCode,
        public int $riskPoints,
        public FraudSeverity $severity,
        public string $description,
    ) {}
}
