<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Queries;

use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Features\Responses\Api\ReadsResponseMetrics;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;

final class ReadResponseMetricsQuery implements ReadsResponseMetrics
{
    public function summary(?string $formId = null): FeedbackAnalyticsData
    {
        $submitted = FeedbackResponseStatus::Submitted->value;
        $reviewed = FeedbackResponseStatus::Reviewed->value;
        $rejected = FeedbackResponseStatus::Rejected->value;
        $spam = FeedbackResponseStatus::Spam->value;

        $row = $this->responseQuery($formId)
            ->selectRaw(
                'COUNT(*) as total, ' .
                'COUNT(CASE WHEN status IN (?, ?) THEN 1 END) as completed, ' .
                'AVG(score) as avg_score, ' .
                'MAX(max_score) as max_score, ' .
                'COUNT(CASE WHEN status = ? THEN 1 END) as pending, ' .
                'COUNT(CASE WHEN status = ? THEN 1 END) as rejected, ' .
                'COUNT(CASE WHEN status = ? THEN 1 END) as spam',
                [$submitted, $reviewed, $submitted, $rejected, $spam],
            )
            ->first();

        $total = (int) ($row?->getAttribute('total') ?? 0);
        $completed = (int) ($row?->getAttribute('completed') ?? 0);
        $avgScore = $row?->getAttribute('avg_score');
        $maxScore = $row?->getAttribute('max_score');

        return new FeedbackAnalyticsData(
            totalResponses: $total,
            completedResponses: $completed,
            averageScore: $avgScore !== null ? round((float) $avgScore, 2) : null,
            maxScore: $maxScore !== null ? round((float) $maxScore, 2) : null,
            completionRate: $total > 0 ? round(($completed / $total) * 100, 2) : 0.0,
            pendingReview: (int) ($row?->getAttribute('pending') ?? 0),
            rejected: (int) ($row?->getAttribute('rejected') ?? 0),
            spam: (int) ($row?->getAttribute('spam') ?? 0),
        );
    }

    public function dashboardMetrics(): array
    {
        $responses = $this->responseQuery(null);
        $completed = (clone $responses)->whereIn('status', [
            FeedbackResponseStatus::Submitted,
            FeedbackResponseStatus::Reviewed,
        ]);
        $driver = (new FeedbackResponse)->getConnection()->getDriverName();
        $date = match ($driver) {
            'sqlite' => 'date(submitted_at)',
            'sqlsrv' => 'CAST(submitted_at AS DATE)',
            default => 'DATE(submitted_at)',
        };

        return [
            'total_responses' => (clone $responses)->count(),
            'submitted_responses' => (clone $completed)->count(),
            'response_trend' => (clone $completed)
                ->selectRaw($date . ' as date, COUNT(*) as count')
                ->whereNotNull('submitted_at')
                ->groupBy('date')
                ->orderByDesc('date')
                ->limit(30)
                ->pluck('count', 'date')
                ->reverse()
                ->toArray(),
            'average_rating' => (clone $completed)->whereNotNull('score')->avg('score'),
        ];
    }

    /** @return Builder<FeedbackResponse> */
    private function responseQuery(?string $formId): Builder
    {
        $query = FeedbackResponse::query();

        if ($formId !== null) {
            $query->where('feedback_form_id', $formId);
        }

        return $query;
    }
}
