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

final class FastConversionRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'FAST_CONVERSION';
    }

    public function analyzeClick(Affiliate $affiliate, Request $request, array $context): ?AffiliateFraudSignal
    {
        return null;
    }

    public function analyzeConversion(AffiliateConversion $conversion, array $context): ?AffiliateFraudSignal
    {
        $config = config('affiliates.fraud.anomaly.conversion_time', []);
        $minSeconds = $config['min_seconds'] ?? 5;

        $attribution = $conversion->attribution;

        if (! $attribution) {
            return null;
        }

        $secondsSinceClick = $attribution->first_seen_at->diffInSeconds($conversion->occurred_at);

        if ($secondsSinceClick < $minSeconds) {
            return AffiliateFraudSignal::create([
                'affiliate_id' => $conversion->affiliate_id,
                'conversion_id' => $conversion->id,
                'rule_code' => $this->ruleCode(),
                'risk_points' => 45,
                'severity' => FraudSeverity::High,
                'description' => "Conversion occurred {$secondsSinceClick}s after click (min: {$minSeconds}s)",
                'evidence' => [
                    'click_time' => $attribution->first_seen_at->toIso8601String(),
                    'conversion_time' => $conversion->occurred_at->toIso8601String(),
                    'seconds' => $secondsSinceClick,
                    'min_seconds' => $minSeconds,
                ],
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now(),
            ]);
        }

        return null;
    }
}
