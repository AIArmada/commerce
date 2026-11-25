<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Extensions;

use AIArmada\Affiliates\Contracts\FraudRule;
use AIArmada\Affiliates\Data\ClickFraudEvidence;
use AIArmada\Affiliates\Data\ConversionFraudEvidence;
use AIArmada\Affiliates\Data\FraudFinding;
use AIArmada\Affiliates\Enums\FraudSeverity;

final class SelfReferralRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'SELF_REFERRAL';
    }

    public function analyzeClick(ClickFraudEvidence $evidence): ?FraudFinding
    {
        return null;
    }

    public function analyzeConversion(ConversionFraudEvidence $evidence): ?FraudFinding
    {
        if (! $evidence->selfReferral) {
            return null;
        }

        return new FraudFinding($this->ruleCode(), 100, FraudSeverity::Critical,
            'Self-referral detected',
            ['affiliate_owner_id' => $evidence->ownerId, 'actor_user_id' => $evidence->actorId],
        );
    }
}
