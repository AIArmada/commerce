<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Events\FraudSignalDetected;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

final class FraudDetectionService
{
    private array $clickRules;

    private array $conversionRules;

    public function __construct(
        #[Tag('affiliates.fraud_rule')]
        iterable $rules = [],
    ) {
        $this->clickRules = [];
        $this->conversionRules = [];

        foreach ($rules as $rule) {
            $this->clickRules[] = $rule;
            $this->conversionRules[] = $rule;
        }
    }

    public function analyzeClick(Affiliate $affiliate, Request $request): array
    {
        if (! config('affiliates.fraud.enabled', true)) {
            return [
                'allowed' => true,
                'score' => 0,
                'signals' => [],
            ];
        }

        $signals = [];
        $context = $this->buildContext($affiliate, $request);

        foreach ($this->clickRules as $rule) {
            $signal = $rule->analyzeClick($affiliate, $request, $context);

            if ($signal !== null) {
                Event::dispatch(new FraudSignalDetected($signal));
                $signals[] = $signal;
            }
        }

        $score = collect($signals)->sum('risk_points');
        $allowed = $score < config('affiliates.fraud.blocking_threshold', 100);

        return [
            'allowed' => $allowed,
            'score' => $score,
            'signals' => $signals,
        ];
    }

    public function analyzeConversion(AffiliateConversion $conversion): array
    {
        if (! config('affiliates.fraud.enabled', true)) {
            return [
                'allowed' => true,
                'score' => 0,
                'signals' => [],
            ];
        }

        $signals = [];
        $context = [
            'conversion' => $conversion,
            'timestamp' => now(),
        ];

        foreach ($this->conversionRules as $rule) {
            $signal = $rule->analyzeConversion($conversion, $context);

            if ($signal !== null) {
                Event::dispatch(new FraudSignalDetected($signal));
                $signals[] = $signal;
            }
        }

        $score = collect($signals)->sum('risk_points');
        $allowed = $score < config('affiliates.fraud.blocking_threshold', 100);

        return [
            'allowed' => $allowed,
            'score' => $score,
            'signals' => $signals,
        ];
    }

    public function getRiskProfile(Affiliate $affiliate): array
    {
        $signals = AffiliateFraudSignal::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('detected_at', '>=', now()->subDays(30))
            ->get();

        $totalScore = $signals->sum('risk_points');
        $severity = FraudSeverity::fromScore($totalScore);

        $byRule = $signals->groupBy('rule_code')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'total_points' => $group->sum('risk_points'),
            ]);

        return [
            'total_score' => $totalScore,
            'severity' => $severity,
            'signal_count' => $signals->count(),
            'by_rule' => $byRule->toArray(),
            'pending_review' => $signals->where('status', FraudSignalStatus::Detected)->count(),
            'confirmed' => $signals->where('status', FraudSignalStatus::Confirmed)->count(),
        ];
    }

    private function buildContext(Affiliate $affiliate, Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'fingerprint' => $this->generateFingerprint($request),
            'referrer' => $request->header('Referer'),
            'timestamp' => now(),
        ];
    }

    private function generateFingerprint(Request $request): string
    {
        return hash('sha256', $request->userAgent() . '|' . $request->ip());
    }
}
