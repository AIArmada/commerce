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
use Illuminate\Support\Facades\Cache;

final class ClickVelocityRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'CLICK_VELOCITY';
    }

    public function analyzeClick(Affiliate $affiliate, Request $request, array $context): ?AffiliateFraudSignal
    {
        $config = config('affiliates.fraud.velocity', []);

        if (! ($config['enabled'] ?? true)) {
            return null;
        }

        $maxPerHour = $config['max_clicks_per_hour'] ?? 100;
        $cacheKey = "fraud:clicks:{$affiliate->id}:{$context['ip_address']}";

        $currentCount = (int) Cache::get($cacheKey, 0);

        if ($currentCount >= $maxPerHour) {
            return AffiliateFraudSignal::create([
                'affiliate_id' => $affiliate->id,
                'rule_code' => $this->ruleCode(),
                'risk_points' => 30,
                'severity' => FraudSeverity::Medium,
                'description' => "Click velocity exceeded: {$currentCount}/{$maxPerHour} per hour",
                'evidence' => [
                    'count' => $currentCount,
                    'limit' => $maxPerHour,
                    'ip_address' => $context['ip_address'],
                ],
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now(),
            ]);
        }

        Cache::put($cacheKey, $currentCount + 1, now()->addHour());

        return null;
    }

    public function analyzeConversion(AffiliateConversion $conversion, array $context): ?AffiliateFraudSignal
    {
        return null;
    }
}
