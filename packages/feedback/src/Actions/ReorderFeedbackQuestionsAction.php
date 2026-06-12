<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackQuestion;

final class ReorderFeedbackQuestionsAction
{
    /**
     * @param  array<string, int>  $order  [id => order_column]
     */
    public function execute(string $formId, array $order): void
    {
        foreach ($order as $id => $position) {
            FeedbackQuestion::where('feedback_form_id', $formId)
                ->where('id', $id)
                ->update(['order_column' => $position]);
        }
    }
}
