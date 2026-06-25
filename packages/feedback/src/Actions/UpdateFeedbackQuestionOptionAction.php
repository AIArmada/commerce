<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackQuestionOption;

final class UpdateFeedbackQuestionOptionAction
{
    public function execute(FeedbackQuestionOption $option, array $data): FeedbackQuestionOption
    {
        $option = OwnerWriteGuard::findOrFailForOwner(FeedbackQuestionOption::class, $option->id);
        unset($data['feedback_question_id'], $data['owner_type'], $data['owner_id']);

        $option->fill($data)->save();

        return $option;
    }
}
