<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Extensions;

use AIArmada\Affiliates\Contracts\FraudRule;
use AIArmada\Affiliates\Data\ClickFraudEvidence;
use AIArmada\Affiliates\Data\ConversionFraudEvidence;
use AIArmada\Affiliates\Data\FraudFinding;
use AIArmada\Affiliates\Enums\FraudSeverity;

final class FingerprintRepeatRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'FINGERPRINT_REPEAT';
    }

    public function analyzeClick(ClickFraudEvidence $evidence): ?FraudFinding
    {
        if ($evidence->fingerprintCount === null || $evidence->fingerprintCount < $evidence->fingerprintLimit) {
            return null;
        }

        return new FraudFinding($this->ruleCode(), 25, FraudSeverity::Medium,
            "Same fingerprint used {$evidence->fingerprintCount} times in 24 hours",
            [
                'fingerprint' => mb_substr($evidence->fingerprint, 0, 16) . '...',
                'count' => $evidence->fingerprintCount,
                'threshold' => $evidence->fingerprintLimit,
            ],
        );
    }

    public function analyzeConversion(ConversionFraudEvidence $evidence): ?FraudFinding
    {
        return null;
    }
}
