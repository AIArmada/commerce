<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Analytics;

use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;

final class RatingDistributionCalculator
{
    public function calculate(FeedbackForm $form, string $questionKey): array
    {
        $answerTable = (new FeedbackAnswer)->getTable();

        $results = $this->baseQuery($form, $questionKey)
            ->selectRaw("{$answerTable}.number_value as rating, COUNT(*) as count")
            ->groupBy("{$answerTable}.number_value")
            ->orderBy("{$answerTable}.number_value")
            ->pluck('count', 'rating')
            ->toArray();

        return $results;
    }

    public function average(FeedbackForm $form, string $questionKey): ?float
    {
        $answerTable = (new FeedbackAnswer)->getTable();

        $avg = $this->baseQuery($form, $questionKey)
            ->avg("{$answerTable}.number_value");

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    private function baseQuery(FeedbackForm $form, string $questionKey): Builder
    {
        $responseTable = (new FeedbackResponse)->getTable();
        $answerTable = (new FeedbackAnswer)->getTable();
        $questionTable = (new FeedbackQuestion)->getTable();

        /** @var Builder<FeedbackResponse> $query */
        $query = FeedbackResponse::query()
            ->where('feedback_form_id', $form->id)
            ->where('status', 'submitted');

        return $query
            ->join($answerTable, "{$answerTable}.feedback_response_id", '=', "{$responseTable}.id")
            ->join($questionTable, "{$questionTable}.id", '=', "{$answerTable}.feedback_question_id")
            ->where("{$questionTable}.key", $questionKey)
            ->whereNotNull("{$answerTable}.number_value");
    }
}
