<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackSection;
use InvalidArgumentException;

final class UpdateFeedbackQuestionAction
{
    public function execute(FeedbackQuestion $question, array $data): FeedbackQuestion
    {
        $question = OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $question->id);
        unset($data['feedback_form_id'], $data['owner_type'], $data['owner_id']);

        $sectionId = $data['feedback_section_id'] ?? null;

        if ($sectionId !== null) {
            $section = OwnerWriteGuard::findOrFailForOwner(FeedbackSection::class, (string) $sectionId);

            if ($section->feedback_form_id !== $question->feedback_form_id) {
                throw new InvalidArgumentException('The feedback section does not belong to the question form.');
            }
        }

        $question->fill($data)->save();

        return $question;
    }
}
