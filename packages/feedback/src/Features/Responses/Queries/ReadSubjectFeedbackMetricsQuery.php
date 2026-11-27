<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Queries;

use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Features\Responses\Api\ReadsSubjectFeedbackMetrics;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;

final readonly class ReadSubjectFeedbackMetricsQuery implements ReadsSubjectFeedbackMetrics
{
    public function average(string $subjectType, string $subjectId, ?string $questionKey = null): ?float
    {
        if ($questionKey !== null) {
            $average = FeedbackAnswer::query()
                ->whereHas('response', function (Builder $query) use ($subjectType, $subjectId): void {
                    $query->where('subject_type', $subjectType)
                        ->where('subject_id', $subjectId)
                        ->whereIn('status', [FeedbackResponseStatus::Submitted, FeedbackResponseStatus::Reviewed]);
                })
                ->whereHas('question', fn (Builder $query) => $query->where('key', $questionKey))
                ->whereNotNull('score')
                ->avg('score');
        } else {
            $average = $this->responses($subjectType, $subjectId)->whereNotNull('score')->avg('score');
        }

        return $average === null ? null : (float) $average;
    }

    public function nps(string $subjectType, string $subjectId, ?string $formId = null): ?int
    {
        $query = $this->responses($subjectType, $subjectId)->whereNotNull('score');

        if ($formId !== null) {
            $query->where('feedback_form_id', $formId);
        }

        $counts = $query->selectRaw('
            COUNT(CASE WHEN score >= 9 THEN 1 END) as promoters,
            COUNT(CASE WHEN score <= 6 THEN 1 END) as detractors,
            COUNT(*) as total
        ')->first();

        $total = (int) ($counts?->getAttribute('total') ?? 0);

        if ($counts === null || $total === 0) {
            return null;
        }

        $promoters = (int) $counts->getAttribute('promoters');
        $detractors = (int) $counts->getAttribute('detractors');

        return (int) round((($promoters - $detractors) / $total) * 100);
    }

    /** @return Builder<FeedbackResponse> */
    private function responses(string $subjectType, string $subjectId): Builder
    {
        return FeedbackResponse::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereIn('status', [FeedbackResponseStatus::Submitted, FeedbackResponseStatus::Reviewed]);
    }
}
