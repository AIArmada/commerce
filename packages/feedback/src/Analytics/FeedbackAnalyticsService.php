<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Analytics;

use AIArmada\Feedback\Contracts\FeedbackAnalyticsCalculator;
use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FeedbackAnalyticsService implements FeedbackAnalyticsCalculator
{
    public function __construct(
        private readonly NpsCalculator $npsCalculator,
        private readonly CsatCalculator $csatCalculator,
        private readonly RatingDistributionCalculator $ratingDistribution,
        private readonly CompletionRateCalculator $completionRate,
    ) {}

    public function summaryForForm(FeedbackForm $form): FeedbackAnalyticsData
    {
        $total = $this->responseQuery($form)->count();
        $completed = $this->responseQuery($form)->where('status', 'submitted')->count();
        $avgScore = $this->responseQuery($form)->whereNotNull('score')->avg('score');
        $maxScore = $this->responseQuery($form)->whereNotNull('max_score')->max('max_score');

        return new FeedbackAnalyticsData(
            totalResponses: $total,
            completedResponses: $completed,
            averageScore: $avgScore !== null ? round((float) $avgScore, 2) : null,
            maxScore: $maxScore !== null ? round((float) $maxScore, 2) : null,
            completionRate: $this->completionRate->calculate($form),
            pendingReview: $this->responseQuery($form)->where('status', 'submitted')->count(),
            rejected: $this->responseQuery($form)->where('status', 'rejected')->count(),
            spam: $this->responseQuery($form)->where('status', 'spam')->count(),
        );
    }

    public function averageForQuestion(FeedbackForm $form, string $questionKey): ?float
    {
        return $this->ratingDistribution->average($form, $questionKey);
    }

    public function distributionForQuestion(FeedbackForm $form, string $questionKey): array
    {
        return $this->ratingDistribution->calculate($form, $questionKey);
    }

    public function latestComments(FeedbackForm $form, int $limit = 10): Collection
    {
        return FeedbackAnswer::query()
            ->whereHas('response', function (Builder $q) use ($form): void {
                $q->where('feedback_form_id', $form->id)
                    ->where('status', 'submitted');
            })
            ->whereNotNull('text_value')
            ->where('text_value', '!=', '')
            ->with(['response', 'question'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function completionRate(FeedbackForm $form): float
    {
        return $this->completionRate->calculate($form);
    }

    public function nps(FeedbackForm $form, ?string $questionKey = null): NpsCalculator
    {
        return $this->npsCalculator;
    }

    public function csat(FeedbackForm $form, ?string $questionKey = null): CsatCalculator
    {
        return $this->csatCalculator;
    }

    /**
     * @return Builder<FeedbackResponse>
     */
    private function responseQuery(FeedbackForm $form): Builder
    {
        /** @var Builder<FeedbackResponse> */
        return FeedbackResponse::query()->where('feedback_form_id', $form->id);
    }
}
