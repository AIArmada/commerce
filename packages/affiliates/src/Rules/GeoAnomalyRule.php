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

final class GeoAnomalyRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'GEO_ANOMALY';
    }

    public function analyzeClick(Affiliate $affiliate, Request $request, array $context): ?AffiliateFraudSignal
    {
        $config = config('affiliates.fraud.anomaly.geo', []);

        if (! ($config['enabled'] ?? false)) {
            return null;
        }

        $lastTouchpoint = AffiliateTouchpoint::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('ip_address', '!=', $context['ip_address'])
            ->latest('touched_at')
            ->first();

        if (! $lastTouchpoint) {
            return null;
        }

        $timeDiff = now()->diffInMinutes($lastTouchpoint->touched_at);

        if ($timeDiff < 5 && $lastTouchpoint->ip_address !== $context['ip_address']) {
            return AffiliateFraudSignal::create([
                'affiliate_id' => $affiliate->id,
                'rule_code' => $this->ruleCode(),
                'risk_points' => 40,
                'severity' => FraudSeverity::High,
                'description' => "Rapid IP change detected within {$timeDiff} minutes",
                'evidence' => [
                    'previous_ip' => $lastTouchpoint->ip_address,
                    'current_ip' => $context['ip_address'],
                    'time_diff_minutes' => $timeDiff,
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
