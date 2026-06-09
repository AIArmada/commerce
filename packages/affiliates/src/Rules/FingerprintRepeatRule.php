<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Rules;

use AIArmada\Affiliates\Contracts\FraudRule;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Illuminate\Http\Request;

final class FingerprintRepeatRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'FINGERPRINT_REPEAT';
    }

    public function analyzeClick(Affiliate $affiliate, Request $request, array $context): ?AffiliateFraudSignal
    {
        $config = config('affiliates.tracking.fingerprint', []);

        if (! ($config['enabled'] ?? false)) {
            return null;
        }

        $threshold = max(1, (int) ($config['threshold'] ?? 5));

        $existingCount = AffiliateTouchpoint::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('metadata->fingerprint', $context['fingerprint'])
            ->where('touched_at', '>=', now()->subHours(24))
            ->count();

        if ($existingCount >= $threshold) {
            return AffiliateFraudSignal::create([
                'affiliate_id' => $affiliate->id,
                'rule_code' => $this->ruleCode(),
                'risk_points' => 25,
                'severity' => FraudSeverity::Medium,
                'description' => "Same fingerprint used {$existingCount} times in 24 hours",
                'evidence' => [
                    'fingerprint' => mb_substr($context['fingerprint'], 0, 16) . '...',
                    'count' => $existingCount,
                    'threshold' => $threshold,
                ],
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now(),
            ]);
        }

        return null;
    }

    public function analyzeConversion(AffiliateConversion $conversion, array $context): ?AffiliateFraudSignal
    {
        return null;
    }
}
