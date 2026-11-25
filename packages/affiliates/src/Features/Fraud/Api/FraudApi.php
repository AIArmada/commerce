<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Api;

interface FraudApi
{
    public function analyzeClick(string $affiliateId, ?string $ipAddress, ?string $userAgent): FraudAssessment;

    public function analyzeConversion(string $conversionId): FraudAssessment;

    /** @return array{total_score: int, severity: \AIArmada\Affiliates\Enums\FraudSeverity, signal_count: int, by_rule: array<string, array{count: int, total_points: int}>, pending_review: int, confirmed: int} */
    public function getRiskProfile(string $affiliateId): array;
}
