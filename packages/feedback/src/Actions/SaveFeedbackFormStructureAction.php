<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackQuestionOption;
use AIArmada\Feedback\Models\FeedbackSection;
use InvalidArgumentException;

final class SaveFeedbackFormStructureAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function saveSection(string $formId, array $data, ?FeedbackSection $section = null): FeedbackSection
    {
        OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $formId);

        if ($section !== null) {
            $section = OwnerWriteGuard::findOrFailForOwner(FeedbackSection::class, $section->id);

            if ($section->feedback_form_id !== $formId) {
                throw new InvalidArgumentException('The feedback section does not belong to the selected form.');
            }
        }

        $section ??= new FeedbackSection;
        $section->fill([
            'feedback_form_id' => $formId,
            'key' => $data['key'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'order_column' => (int) ($data['order_column'] ?? 0),
            'settings' => $data['settings'] ?? [],
            'metadata' => $data['metadata'] ?? [],
        ])->save();

        return $section;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveQuestion(string $formId, array $data, ?FeedbackQuestion $question = null): FeedbackQuestion
    {
        OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $formId);

        if ($question !== null) {
            $question = OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $question->id);

            if ($question->feedback_form_id !== $formId) {
                throw new InvalidArgumentException('The feedback question does not belong to the selected form.');
            }
        }

        $sectionId = array_key_exists('feedback_section_id', $data)
            ? $data['feedback_section_id']
            : null;

        if ($sectionId !== null) {
            $section = OwnerWriteGuard::findOrFailForOwner(FeedbackSection::class, (string) $sectionId);

            if ($section->feedback_form_id !== $formId) {
                throw new InvalidArgumentException('The feedback section does not belong to the selected form.');
            }
        }

        $question ??= new FeedbackQuestion;
        $question->fill([
            'feedback_form_id' => $formId,
            'feedback_section_id' => $sectionId,
            'key' => $data['key'],
            'type' => $data['type'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'help_text' => $data['help_text'] ?? null,
            'placeholder' => $data['placeholder'] ?? null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_scored' => (bool) ($data['is_scored'] ?? false),
            'order_column' => (int) ($data['order_column'] ?? 0),
            'validation_rules' => $data['validation_rules'] ?? [],
            'visibility_rules' => $data['visibility_rules'] ?? [],
            'scoring_rules' => $data['scoring_rules'] ?? [],
            'settings' => $data['settings'] ?? [],
            'metadata' => $data['metadata'] ?? [],
        ])->save();

        return $question;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveOption(
        string $questionId,
        array $data,
        ?FeedbackQuestionOption $option = null,
    ): FeedbackQuestionOption {
        OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $questionId);

        if ($option !== null) {
            $option = OwnerWriteGuard::findOrFailForOwner(FeedbackQuestionOption::class, $option->id);

            if ($option->feedback_question_id !== $questionId) {
                throw new InvalidArgumentException('The feedback option does not belong to the selected question.');
            }
        }

        $option ??= new FeedbackQuestionOption;
        $option->fill([
            'feedback_question_id' => $questionId,
            'label' => $data['label'],
            'value' => $data['value'],
            'score' => $data['score'] ?? null,
            'order_column' => (int) ($data['order_column'] ?? 0),
            'metadata' => $data['metadata'] ?? [],
        ])->save();

        return $option;
    }
}
