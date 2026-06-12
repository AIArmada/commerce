<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Analytics;

use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;

final class RatingDistributionCalculator
{
    public function calculate(FeedbackForm $form, string $questionKey): array
    {
        $results = $this->baseQuery($form, $questionKey)
            ->selectRaw('answers.number_value as rating, COUNT(*) as count')
            ->groupBy('answers.number_value')
            ->orderBy('answers.number_value')
            ->pluck('count', 'rating')
            ->toArray();

        return $results;
    }

    public function average(FeedbackForm $form, string $questionKey): ?float
    {
        $avg = $this->baseQuery($form, $questionKey)
            ->avg('answers.number_value');

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    private function baseQuery(FeedbackForm $form, string $questionKey): Builder
    {
        /** @var Builder<FeedbackResponse> $query */
        $query = FeedbackResponse::query()
            ->where('feedback_form_id', $form->id)
            ->where('status', 'submitted');

        return $query
            ->join('feedback_answers as answers', 'answers.feedback_response_id', '=', 'feedback_responses.id')
            ->join('feedback_questions as questions', 'questions.id', '=', 'answers.feedback_question_id')
            ->where('questions.key', $questionKey)
            ->whereNotNull('answers.number_value');
    }
}
