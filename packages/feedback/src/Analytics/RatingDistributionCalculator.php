<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Analytics;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;

final class RatingDistributionCalculator
{
    public function calculate(?FeedbackForm $form = null, ?string $questionKey = null): array
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

    public function average(?FeedbackForm $form = null, ?string $questionKey = null): ?float
    {
        $answerTable = (new FeedbackAnswer)->getTable();

        $avg = $this->baseQuery($form, $questionKey)
            ->avg("{$answerTable}.number_value");

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    private function baseQuery(?FeedbackForm $form = null, ?string $questionKey = null): Builder
    {
        $responseTable = (new FeedbackResponse)->getTable();
        $answerTable = (new FeedbackAnswer)->getTable();
        $questionTable = (new FeedbackQuestion)->getTable();

        /** @var Builder<FeedbackResponse> $query */
        $query = FeedbackResponse::query()->where('status', 'submitted');

        if ($form !== null) {
            $query->where("{$responseTable}.feedback_form_id", $form->id);
        }

        $query = $query
            ->join($answerTable, "{$answerTable}.feedback_response_id", '=', "{$responseTable}.id")
            ->join($questionTable, "{$questionTable}.id", '=', "{$answerTable}.feedback_question_id");

        $owner = OwnerContext::resolve();
        OwnerContext::assertResolvedOrExplicitGlobal($owner);
        OwnerQuery::applyToQueryBuilder(
            $query->getQuery(),
            $owner,
            ownerTypeColumn: "{$answerTable}.owner_type",
            ownerIdColumn: "{$answerTable}.owner_id",
        );
        OwnerQuery::applyToQueryBuilder(
            $query->getQuery(),
            $owner,
            ownerTypeColumn: "{$questionTable}.owner_type",
            ownerIdColumn: "{$questionTable}.owner_id",
        );

        if ($questionKey !== null) {
            $query->where("{$questionTable}.key", $questionKey);
        }

        return $query
            ->whereNotNull("{$answerTable}.number_value");
    }
}
