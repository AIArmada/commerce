<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestionOption;

final readonly class ReorderFeedbackQuestionOptionsAction
{
    /**
     * @param  array<string, int>  $order  [id => order_column]
     */
    public function execute(string $questionId, array $order): void
    {
        (new FeedbackQuestion)->getConnection()->transaction(function () use ($questionId, $order): void {
            if (FeedbackQuestion::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $questionId);
            }

            $question = FeedbackQuestion::query()->whereKey($questionId)->firstOrFail();
            FeedbackForm::query()->whereKey($question->feedback_form_id)->lockForUpdate()->firstOrFail();
            FeedbackQuestion::query()->whereKey($questionId)->lockForUpdate()->firstOrFail();

            foreach ($order as $id => $position) {
                FeedbackQuestionOption::where('feedback_question_id', $questionId)
                    ->where('id', $id)
                    ->update(['order_column' => $position]);
            }
        });
    }
}
