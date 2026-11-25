<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Extensions;

use AIArmada\Affiliates\Contracts\FraudRule;
use AIArmada\Affiliates\Data\ClickFraudEvidence;
use AIArmada\Affiliates\Data\ConversionFraudEvidence;
use AIArmada\Affiliates\Data\FraudFinding;
use AIArmada\Affiliates\Enums\FraudSeverity;

final class ConversionVelocityRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'CONVERSION_VELOCITY';
    }

    public function analyzeClick(ClickFraudEvidence $evidence): ?FraudFinding
    {
        return null;
    }

    public function analyzeConversion(ConversionFraudEvidence $evidence): ?FraudFinding
    {
        if ($evidence->dailyCount < $evidence->dailyLimit) {
            return null;
        }

        return new FraudFinding($this->ruleCode(), 35, FraudSeverity::Medium,
            "Daily conversion limit exceeded: {$evidence->dailyCount}/{$evidence->dailyLimit}",
            ['count' => $evidence->dailyCount, 'limit' => $evidence->dailyLimit, 'date' => $evidence->date],
        );
    }
}
