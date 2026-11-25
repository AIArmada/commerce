<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Data\FraudFinding;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Carbon\CarbonImmutable;

final readonly class RecordFraudFinding
{
    public function execute(string $affiliateId, ?string $conversionId, FraudFinding $finding): AffiliateFraudSignal
    {
        return AffiliateFraudSignal::create([
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
    }
}
