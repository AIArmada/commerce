<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Actions;

use AIArmada\Affiliates\Data\FraudFinding;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalRecord;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use Carbon\CarbonImmutable;

final readonly class RecordFraudFinding
{
    public function execute(string $affiliateId, ?string $conversionId, FraudFinding $finding): FraudSignalRecord
    {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliateId,
            'conversion_id' => $conversionId,
            'rule_code' => $finding->ruleCode,
            'risk_points' => $finding->riskPoints,
            'severity' => $finding->severity,
            'description' => $finding->description,
            'evidence' => $finding->evidence,
            'status' => FraudSignalStatus::Detected,
            'detected_at' => CarbonImmutable::now(),
        ]);

        return new FraudSignalRecord((string) $signal->getKey(), $affiliateId, $conversionId, $finding->ruleCode, $finding->riskPoints, $finding->severity, $finding->description);
    }
}
