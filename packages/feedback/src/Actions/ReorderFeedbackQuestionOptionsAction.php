<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackQuestionOption;

final class ReorderFeedbackQuestionOptionsAction
{
    /**
     * @param  array<string, int>  $order  [id => order_column]
     */
    public function execute(string $questionId, array $order): void
    {
        foreach ($order as $id => $position) {
            FeedbackQuestionOption::where('feedback_question_id', $questionId)
                ->where('id', $id)
                ->update(['order_column' => $position]);
        }
    }
}
