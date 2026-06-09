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

final class ConversionVelocityRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'CONVERSION_VELOCITY';
    }

    public function analyzeClick(Affiliate $affiliate, Request $request, array $context): ?AffiliateFraudSignal
    {
        return null;
    }

    public function analyzeConversion(AffiliateConversion $conversion, array $context): ?AffiliateFraudSignal
    {
        $config = config('affiliates.fraud.velocity', []);
        $maxDaily = $config['max_conversions_per_day'] ?? 50;

        $affiliate = $conversion->affiliate;
        $todayCount = $affiliate->conversions()
            ->whereDate('occurred_at', today())
            ->count();

        if ($todayCount >= $maxDaily) {
            return AffiliateFraudSignal::create([
                'affiliate_id' => $affiliate->id,
                'conversion_id' => $conversion->id,
                'rule_code' => $this->ruleCode(),
                'risk_points' => 35,
                'severity' => FraudSeverity::Medium,
                'description' => "Daily conversion limit exceeded: {$todayCount}/{$maxDaily}",
                'evidence' => [
                    'count' => $todayCount,
                    'limit' => $maxDaily,
                    'date' => today()->toDateString(),
                ],
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now(),
            ]);
        }

        return null;
    }
}
