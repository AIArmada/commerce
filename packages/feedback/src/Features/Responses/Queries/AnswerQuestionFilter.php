<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Read-only relational projection for analytics. Forms owns all question writes.
 * Correlate the question to the answer's response form, even on dashboard reads.
 */
final class AnswerQuestionFilter
{
    /**
     * @param  Builder<FeedbackAnswer>  $answers
     * @param  list<string>  $types
     * @return Builder<FeedbackAnswer>
     */
    public static function apply(Builder $answers, ?string $formId = null, ?string $questionKey = null, array $types = []): Builder
    {
        $answerTable = $answers->getModel()->getTable();
        $responseTable = (new FeedbackResponse)->getTable();
        $questionTable = (string) config('feedback.database.table_prefix', '')
            . (string) config('feedback.database.tables.questions', 'feedback_questions');
        $questionConfig = FeedbackAnswer::ownerScopeConfig();
        $responseConfig = FeedbackResponse::ownerScopeConfig();

        return $answers->whereExists(function (QueryBuilder $questions) use ($answerTable, $responseTable, $questionTable, $questionConfig, $responseConfig, $formId, $questionKey, $types): void {
            $questions->selectRaw('1')
                ->from($questionTable, 'feedback_metric_question')
                ->whereColumn('feedback_metric_question.id', $answerTable . '.feedback_question_id')
                ->whereExists(function (QueryBuilder $responses) use ($answerTable, $responseTable, $responseConfig): void {
                    $responses->selectRaw('1')->from($responseTable, 'feedback_metric_response')
                        ->whereColumn('feedback_metric_response.id', $answerTable . '.feedback_response_id')
                        ->whereColumn('feedback_metric_response.feedback_form_id', 'feedback_metric_question.feedback_form_id')
                        ->whereIn('feedback_metric_response.status', [
                            FeedbackResponseStatus::Submitted->value,
                            FeedbackResponseStatus::Reviewed->value,
                        ]);

                    if ($responseConfig->enabled) {
                        $owner = OwnerContext::resolveConfigured($responseConfig);
                        OwnerContext::assertResolvedOrExplicitGlobal($owner);
                        OwnerQuery::applyToQueryBuilder(
                            $responses,
                            $owner,
                            OwnerScopeOverride::suppressIncludeGlobal() ? false : $responseConfig->includeGlobal,
                            'feedback_metric_response.' . $responseConfig->ownerTypeColumn,
                            'feedback_metric_response.' . $responseConfig->ownerIdColumn,
                        );
                    }
                });

            if ($questionConfig->enabled) {
                $owner = OwnerContext::resolveConfigured($questionConfig);
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                OwnerQuery::applyToQueryBuilder(
                    $questions,
                    $owner,
                    OwnerScopeOverride::suppressIncludeGlobal() ? false : $questionConfig->includeGlobal,
                    'feedback_metric_question.' . $questionConfig->ownerTypeColumn,
                    'feedback_metric_question.' . $questionConfig->ownerIdColumn,
                );
            }

            if ($formId !== null) {
                $questions->where('feedback_metric_question.feedback_form_id', $formId);
            }

            if ($questionKey !== null) {
                $questions->where('feedback_metric_question.key', $questionKey);
            }

            if ($types !== []) {
                $questions->whereIn('feedback_metric_question.type', $types);
            }
        });
    }
}
