<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Rules;

use AIArmada\Affiliates\Contracts\FraudRule;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Illuminate\Http\Request;

final class SelfReferralRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'SELF_REFERRAL';
    }

    public function analyzeClick(Affiliate $affiliate, Request $request, array $context): ?AffiliateFraudSignal
    {
        return null;
    }

    public function analyzeConversion(AffiliateConversion $conversion, array $context): ?AffiliateFraudSignal
    {
        if (! config('affiliates.tracking.block_self_referral', false)) {
            return null;
        }

        $affiliate = $conversion->affiliate;

        if ($affiliate->owner_id && $conversion->owner_id === $affiliate->owner_id) {
            return AffiliateFraudSignal::create([
                'affiliate_id' => $affiliate->id,
                'conversion_id' => $conversion->id,
                'rule_code' => $this->ruleCode(),
                'risk_points' => 100,
                'severity' => FraudSeverity::Critical,
                'description' => 'Self-referral detected',
                'evidence' => [
                    'affiliate_owner_id' => $affiliate->owner_id,
                    'conversion_owner_id' => $conversion->owner_id,
                ],
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now(),
            ]);
        }

        return null;
    }
}
