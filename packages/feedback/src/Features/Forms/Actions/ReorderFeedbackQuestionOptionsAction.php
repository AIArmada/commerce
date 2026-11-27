<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestionOption;

final class ReorderFeedbackQuestionOptionsAction
{
    /**
     * @param  array<string, int>  $order  [id => order_column]
     */
    public function execute(string $questionId, array $order): void
    {
        OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $questionId);

        foreach ($order as $id => $position) {
            FeedbackQuestionOption::where('feedback_question_id', $questionId)
                ->where('id', $id)
                ->update(['order_column' => $position]);
        }
    }
}
