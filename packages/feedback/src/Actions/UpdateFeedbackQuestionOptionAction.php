<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackQuestionOption;

final class UpdateFeedbackQuestionOptionAction
{
    public function execute(FeedbackQuestionOption $option, array $data): FeedbackQuestionOption
    {
        $option->fill($data)->save();

        return $option;
    }
}
