<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final class FeedbackAnalyticsData
{
    public function __construct(
        public readonly int $totalResponses,
        public readonly int $completedResponses,
        public readonly ?float $averageScore,
        public readonly ?float $maxScore,
        public readonly float $completionRate,
        public readonly int $pendingReview,
        public readonly int $rejected,
        public readonly int $spam,
    ) {}
}
