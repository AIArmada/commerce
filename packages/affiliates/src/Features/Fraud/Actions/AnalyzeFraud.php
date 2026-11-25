<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Actions;

use AIArmada\Affiliates\Contracts\FraudRule;
use AIArmada\Affiliates\Features\Fraud\Api\FraudApi;
use AIArmada\Affiliates\Features\Fraud\Api\FraudAssessment;
use AIArmada\Affiliates\Features\Fraud\Queries\FraudEvidenceQuery;
use AIArmada\Affiliates\Features\Fraud\Queries\FraudRiskProfileQuery;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Support\IpHasher;
use Illuminate\Container\Attributes\Tag;

final readonly class AnalyzeFraud implements FraudApi
{
    /** @var list<FraudRule> */
    private array $rules;

    /** @param iterable<FraudRule> $rules */
    public function __construct(
        #[Tag('affiliates.fraud_rule')]
        iterable $rules = [],
        private FraudEvidenceQuery $evidence = new FraudEvidenceQuery,
        private CountFraudClick $counter = new CountFraudClick,
        private RecordFraudFinding $record = new RecordFraudFinding,
        private FraudRiskProfileQuery $profile = new FraudRiskProfileQuery,
    ) {
        $this->rules = array_values(is_array($rules) ? $rules : iterator_to_array($rules, false));
    }

    public function analyzeClick(string $affiliateId, ?string $ipAddress, ?string $userAgent): FraudAssessment
    {
        if (! config('affiliates.fraud.enabled', true)) {
            return new FraudAssessment(true, 0, []);
        }

        Affiliate::query()->whereKey($affiliateId)->firstOrFail(['id']);

        $signals = [];
        $ip = IpHasher::hash($ipAddress);
        $fingerprint = hash('sha256', $userAgent . '|' . $ipAddress);
        $count = config('affiliates.fraud.velocity.enabled', true) ? $this->counter->execute($affiliateId, $ip) : null;
        $evidence = $this->evidence->click($affiliateId, $ip, $fingerprint, $count);

        foreach ($this->rules as $rule) {
            $finding = $rule->analyzeClick($evidence);

            if ($finding !== null) {
                $signals[] = $this->record->execute($affiliateId, null, $finding);
            }
        }

        return $this->assessment($signals);
    }

    public function analyzeConversion(string $conversionId): FraudAssessment
    {
        if (! config('affiliates.fraud.enabled', true)) {
            return new FraudAssessment(true, 0, []);
        }

        $conversion = AffiliateConversion::query()->with(['affiliate', 'attribution'])->findOrFail($conversionId);
        $evidence = $this->evidence->conversion($conversion);
        $signals = [];

        foreach ($this->rules as $rule) {
            $finding = $rule->analyzeConversion($evidence);

            if ($finding !== null) {
                $signals[] = $this->record->execute((string) $conversion->affiliate_id, $conversionId, $finding);
            }
        }

        return $this->assessment($signals);
    }

    public function getRiskProfile(string $affiliateId): array
    {
        return $this->profile->execute($affiliateId);
    }

    /** @param list<\AIArmada\Affiliates\Features\Fraud\Api\FraudSignalRecord> $signals */
    private function assessment(array $signals): FraudAssessment
    {
        $score = array_sum(array_map(static fn ($signal): int => $signal->riskPoints, $signals));

        return new FraudAssessment($score < (int) config('affiliates.fraud.blocking_threshold', 100), $score, $signals);
    }
}
