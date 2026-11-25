<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Queries;

use AIArmada\Affiliates\Features\Fraud\Api\FraudSeverity;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use Carbon\CarbonImmutable;

final class FraudRiskProfileQuery
{
    public function hasUnresolvedSignals(string $affiliateId): bool
    {
        return AffiliateFraudSignal::query()
            ->where('affiliate_id', $affiliateId)
            ->whereIn('status', [FraudSignalStatus::Detected, FraudSignalStatus::Confirmed])
            ->exists();
    }

    public function execute(string $affiliateId): array
    {
        $rows = AffiliateFraudSignal::query()
            ->where('affiliate_id', $affiliateId)
            ->where('detected_at', '>=', CarbonImmutable::now()->subDays(30))
            ->toBase()
            ->selectRaw('rule_code, status, COUNT(*) as signal_count, SUM(risk_points) as total_points')
            ->groupBy('rule_code', 'status')
            ->get();

        $totalScore = $signalCount = $pendingReview = $confirmed = 0;
        $byRule = [];

        foreach ($rows as $row) {
            $count = (int) $row->signal_count;
            $points = (int) $row->total_points;
            $rule = (string) $row->rule_code;
            $totalScore += $points;
            $signalCount += $count;
            $byRule[$rule] ??= ['count' => 0, 'total_points' => 0];
            $byRule[$rule]['count'] += $count;
            $byRule[$rule]['total_points'] += $points;

            if ($row->status === FraudSignalStatus::Detected->value) {
                $pendingReview += $count;
            }

            if ($row->status === FraudSignalStatus::Confirmed->value) {
                $confirmed += $count;
            }
        }

        return [
            'total_score' => $totalScore,
            'severity' => FraudSeverity::fromScore($totalScore),
            'signal_count' => $signalCount,
            'by_rule' => $byRule,
            'pending_review' => $pendingReview,
            'confirmed' => $confirmed,
        ];
    }
}
