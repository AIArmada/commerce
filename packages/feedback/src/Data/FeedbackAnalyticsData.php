<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final readonly class FeedbackAnalyticsData
{
    public function __construct(
        public int $totalResponses,
        public int $completedResponses,
        public ?float $averageScore,
        public ?float $maxScore,
        public float $completionRate,
        public int $pendingReview,
        public int $rejected,
        public int $spam,
    ) {}
}
