<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Rules;

use AIArmada\Affiliates\Contracts\FraudRule;
use AIArmada\Affiliates\Data\ClickFraudEvidence;
use AIArmada\Affiliates\Data\ConversionFraudEvidence;
use AIArmada\Affiliates\Data\FraudFinding;
use AIArmada\Affiliates\Enums\FraudSeverity;

final class ClickVelocityRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'CLICK_VELOCITY';
    }

    public function analyzeClick(ClickFraudEvidence $evidence): ?FraudFinding
    {
        if ($evidence->clickCount === null || $evidence->clickCount < $evidence->clickLimit) {
            return null;
        }

        return new FraudFinding($this->ruleCode(), 30, FraudSeverity::Medium,
            "Click velocity exceeded: {$evidence->clickCount}/{$evidence->clickLimit} per hour",
            ['count' => $evidence->clickCount, 'limit' => $evidence->clickLimit, 'ip_address' => $evidence->ipAddress],
        );
    }

    public function analyzeConversion(ConversionFraudEvidence $evidence): ?FraudFinding
    {
        return null;
    }
}
