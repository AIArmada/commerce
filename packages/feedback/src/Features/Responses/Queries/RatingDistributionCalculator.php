<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Queries;

use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use Illuminate\Database\Eloquent\Builder;

final class RatingDistributionCalculator
{
    public function calculate(?string $formId = null, ?string $questionKey = null): array
    {
        $answerTable = (new FeedbackAnswer)->getTable();

        $results = $this->baseQuery($formId, $questionKey)
            ->selectRaw("{$answerTable}.number_value as rating, COUNT(*) as count")
            ->groupBy("{$answerTable}.number_value")
            ->orderBy("{$answerTable}.number_value")
            ->pluck('count', 'rating')
            ->toArray();

        return $results;
    }

    public function average(?string $formId = null, ?string $questionKey = null): ?float
    {
        $answerTable = (new FeedbackAnswer)->getTable();

        $avg = $this->baseQuery($formId, $questionKey)
            ->avg("{$answerTable}.number_value");

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    private function baseQuery(?string $formId = null, ?string $questionKey = null): Builder
    {
        return AnswerQuestionFilter::apply(
            FeedbackAnswer::query()->whereNotNull('number_value'),
            $formId,
            $questionKey,
        );
    }
}
