<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackQuestion;

final class UpdateFeedbackQuestionAction
{
    public function execute(FeedbackQuestion $question, array $data): FeedbackQuestion
    {
        $question->fill($data)->save();

        return $question;
    }
}
