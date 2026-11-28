<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Queries;

use AIArmada\Feedback\Data\CsatResultData;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;

final class CsatCalculator
{
    public function calculate(?string $formId = null, ?string $questionKey = null): CsatResultData
    {
        $query = $questionKey !== null
            ? $this->answerQuery($formId, $questionKey)
            : $this->baseQuery($formId);

        $counts = (clone $query)
            ->selectRaw('
                COUNT(CASE WHEN score >= 4 THEN 1 END) as satisfied,
                COUNT(CASE WHEN score = 3 THEN 1 END) as neutral,
                COUNT(CASE WHEN score <= 2 THEN 1 END) as unsatisfied,
                COUNT(*) as total,
                AVG(score) as average
            ')
            ->first();

        $total = (int) ($counts->total ?? 0);

        if ($total === 0) {
            return new CsatResultData(
                score: null,
                satisfiedCount: 0,
                neutralCount: 0,
                unsatisfiedCount: 0,
                responseCount: 0,
                average: null,
                distribution: [],
            );
        }

        $satisfied = (int) ($counts->satisfied ?? 0);
        $csatScore = round(($satisfied / $total) * 100, 2);

        $distributionQuery = (clone $query)
            ->selectRaw('score, COUNT(*) as count')
            ->groupBy('score')
            ->orderBy('score')
            ->pluck('count', 'score')
            ->toArray();

        return new CsatResultData(
            score: $csatScore,
            satisfiedCount: $satisfied,
            neutralCount: (int) ($counts->neutral ?? 0),
            unsatisfiedCount: (int) ($counts->unsatisfied ?? 0),
            responseCount: $total,
            average: $counts !== null && isset($counts->average) ? round((float) $counts->average, 2) : null,
            distribution: $distributionQuery,
        );
    }

    private function baseQuery(?string $formId = null): Builder
    {
        /** @var Builder<FeedbackResponse> $query */
        $query = FeedbackResponse::query()
            ->whereIn('status', ['submitted', 'reviewed'])
            ->whereNotNull('score');

        if ($formId !== null) {
            $query->where('feedback_form_id', $formId);
        }

        return $query;
    }

    /**
     * @return Builder<FeedbackAnswer>
     */
    private function answerQuery(?string $formId, string $questionKey): Builder
    {
        /** @var Builder<FeedbackAnswer> $query */
        $query = FeedbackAnswer::query()->whereNotNull('score');

        AnswerQuestionFilter::apply($query, $formId, $questionKey);

        return $query;
    }
}
