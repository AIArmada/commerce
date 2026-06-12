<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackQuestion;

final class DeleteFeedbackQuestionAction
{
    public function execute(FeedbackQuestion $question): void
    {
        $question->options()->delete();
        $question->answers()->delete();
        $question->delete();
    }
}
