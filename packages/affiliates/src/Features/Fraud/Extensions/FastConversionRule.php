<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Extensions;

use AIArmada\Affiliates\Contracts\FraudRule;
use AIArmada\Affiliates\Data\ClickFraudEvidence;
use AIArmada\Affiliates\Data\ConversionFraudEvidence;
use AIArmada\Affiliates\Data\FraudFinding;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSeverity;

final class FastConversionRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'FAST_CONVERSION';
    }

    public function analyzeClick(ClickFraudEvidence $evidence): ?FraudFinding
    {
        return null;
    }

    public function analyzeConversion(ConversionFraudEvidence $evidence): ?FraudFinding
    {
        if ($evidence->clickedAt === null || $evidence->occurredAt === null) {
            return null;
        }

        $seconds = $evidence->clickedAt->diffInSeconds($evidence->occurredAt);

        if ($seconds >= $evidence->minimumSeconds) {
            return null;
        }

        return new FraudFinding(
            $this->ruleCode(),
            45,
            FraudSeverity::High,
            "Conversion occurred {$seconds}s after click (min: {$evidence->minimumSeconds}s)",
            [
                'click_time' => $evidence->clickedAt->toIso8601String(),
                'conversion_time' => $evidence->occurredAt->toIso8601String(),
                'seconds' => $seconds,
                'min_seconds' => $evidence->minimumSeconds,
            ],
        );
    }
}
