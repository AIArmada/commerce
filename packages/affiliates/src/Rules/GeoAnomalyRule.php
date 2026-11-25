<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Rules;

use AIArmada\Affiliates\Contracts\FraudRule;
use AIArmada\Affiliates\Data\ClickFraudEvidence;
use AIArmada\Affiliates\Data\ConversionFraudEvidence;
use AIArmada\Affiliates\Data\FraudFinding;
use AIArmada\Affiliates\Enums\FraudSeverity;

final class GeoAnomalyRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'GEO_ANOMALY';
    }

    public function analyzeClick(ClickFraudEvidence $evidence): ?FraudFinding
    {
        $minutes = $evidence->minutesSincePreviousIp;

        if ($minutes === null || $minutes < 0 || $minutes >= 5 || $evidence->previousIp === $evidence->ipAddress) {
            return null;
        }

        return new FraudFinding($this->ruleCode(), 40, FraudSeverity::High,
            "Rapid IP change detected within {$minutes} minutes",
            ['previous_ip' => $evidence->previousIp, 'current_ip' => $evidence->ipAddress, 'time_diff_minutes' => $minutes],
        );
    }

    public function analyzeConversion(ConversionFraudEvidence $evidence): ?FraudFinding
    {
        return null;
    }
}
