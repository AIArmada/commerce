<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackQuestionOption;

final class CreateFeedbackQuestionOptionAction
{
    public function execute(
        string $questionId,
        string $label,
        string $value,
        ?float $score = null,
        int $orderColumn = 0,
    ): FeedbackQuestionOption {
        OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $questionId);

        return FeedbackQuestionOption::create([
            'feedback_question_id' => $questionId,
            'label' => $label,
            'value' => $value,
            'score' => $score,
            'order_column' => $orderColumn,
        ]);
    }
}
