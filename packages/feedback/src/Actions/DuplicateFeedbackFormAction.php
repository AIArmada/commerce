<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Models\FeedbackForm;

final class DuplicateFeedbackFormAction
{
    public function __construct(
        private readonly CreateFeedbackFormAction $createForm,
        private readonly CreateFeedbackSectionAction $createSection,
        private readonly CreateFeedbackQuestionAction $createQuestion,
        private readonly CreateFeedbackQuestionOptionAction $createOption,
    ) {}

    public function execute(FeedbackForm $source, array $overrides = []): FeedbackForm
    {
        $form = $this->createForm->execute(new CreateFeedbackFormData(
            name: $overrides['name'] ?? ($source->name . ' (Copy)'),
            purpose: $overrides['purpose'] ?? $source->purpose,
            status: 'draft',
            visibility: $source->visibility,
            isAnonymousAllowed: $source->is_anonymous_allowed,
            isAnonymityOptional: $source->is_anonymity_optional,
            isLoginRequired: $source->is_login_required,
            isOneResponsePerRespondent: $source->is_one_response_per_respondent,
            isEditAfterSubmitAllowed: $source->is_edit_after_submit_allowed,
            settings: $source->settings ?? [],
        ));

        $source->load('sections.questions.options');

        foreach ($source->sections as $section) {
            $newSection = $this->createSection->execute(
                $form->id,
                $section->title,
                $section->key,
            );

            foreach ($section->questions as $question) {
                $newQuestion = $this->createQuestion->execute(
                    formId: $form->id,
                    key: $question->key,
                    type: $question->type,
                    label: $question->label,
                    sectionId: $newSection->id,
                    description: $question->description,
                    helpText: $question->help_text,
                    placeholder: $question->placeholder,
                    isRequired: $question->is_required,
                    isScored: $question->is_scored,
                    orderColumn: $question->order_column,
                    validationRules: $question->validation_rules ?? [],
                    visibilityRules: $question->visibility_rules ?? [],
                    scoringRules: $question->scoring_rules ?? [],
                    settings: $question->settings ?? [],
                );

                foreach ($question->options as $option) {
                    $this->createOption->execute(
                        questionId: $newQuestion->id,
                        label: $option->label,
                        value: $option->value,
                        score: $option->score,
                        orderColumn: $option->order_column,
                    );
                }
            }
        }

        return $form->fresh();
    }
}
