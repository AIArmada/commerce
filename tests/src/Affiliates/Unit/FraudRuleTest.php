<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\ClickFraudEvidence;
use AIArmada\Affiliates\Features\Fraud\Extensions\GeoAnomalyRule;

test('rapid IP rule rejects old and future evidence without database access', function (): void {
    $rule = new GeoAnomalyRule;

    foreach ([null, -2.0, 5.0, 60.0] as $minutes) {
        expect($rule->analyzeClick(new ClickFraudEvidence('current', 'fingerprint', null, 100, null, 5, 'previous', $minutes)))->toBeNull();
    }

    expect($rule->analyzeClick(new ClickFraudEvidence('current', 'fingerprint', null, 100, null, 5, 'previous', 3.0))?->riskPoints)->toBe(40)
        ->and($rule->analyzeClick(new ClickFraudEvidence('current', 'fingerprint', null, 100, null, 5, 'current', 3.0)))->toBeNull();
});
