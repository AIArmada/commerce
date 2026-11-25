<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Api;

use AIArmada\Affiliates\Enums\FraudSeverity;

interface FraudApi
{
    public function analyzeClick(string $affiliateId, ?string $ipAddress, ?string $userAgent): FraudAssessment;

    public function analyzeConversion(string $conversionId): FraudAssessment;

    public function hasUnresolvedSignals(string $affiliateId): bool;

    /** @return array{total_score: int, severity: FraudSeverity, signal_count: int, by_rule: array<string, array{count: int, total_points: int}>, pending_review: int, confirmed: int} */
    public function getRiskProfile(string $affiliateId): array;
}
