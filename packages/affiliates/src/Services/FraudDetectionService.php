<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Actions\Affiliates\CountFraudClick;
use AIArmada\Affiliates\Actions\Affiliates\RecordFraudFinding;
use AIArmada\Affiliates\Contracts\FraudRule;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Queries\FraudEvidenceQuery;
use AIArmada\Affiliates\Support\IpHasher;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Http\Request;

final class FraudDetectionService
{
    /** @var list<FraudRule> */
    private array $clickRules;

    /** @var list<FraudRule> */
    private array $conversionRules;

    public function __construct(
        #[Tag('affiliates.fraud_rule')]
        iterable $rules = [],
        private readonly FraudEvidenceQuery $evidence = new FraudEvidenceQuery,
        private readonly CountFraudClick $counter = new CountFraudClick,
        private readonly RecordFraudFinding $record = new RecordFraudFinding,
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
        $ip = IpHasher::hash($request->ip());
        $fingerprint = hash('sha256', $request->userAgent() . '|' . $request->ip());
        $count = config('affiliates.fraud.velocity.enabled', true) ? $this->counter->execute((string) $affiliate->id, $ip) : null;
        $evidence = $this->evidence->click((string) $affiliate->id, $ip, $fingerprint, $count);

        foreach ($this->clickRules as $rule) {
            $finding = $rule->analyzeClick($evidence);

            if ($finding !== null) {
                $signals[] = $this->record->execute((string) $affiliate->id, null, $finding);
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
        $evidence = $this->evidence->conversion($conversion);

        foreach ($this->conversionRules as $rule) {
            $finding = $rule->analyzeConversion($evidence);

            if ($finding !== null) {
                $signals[] = $this->record->execute((string) $conversion->affiliate_id, (string) $conversion->id, $finding);
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
            ->where('detected_at', '>=', CarbonImmutable::now()->subDays(30))
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

}
