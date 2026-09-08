<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Models\FeedbackForm;
use Illuminate\Support\Facades\DB;

final class DuplicateFeedbackFormAction
{
    public function __construct(
        private readonly CreateFeedbackFormAction $createForm,
        private readonly SaveFeedbackFormStructureAction $saveStructure,
    ) {}

    public function execute(FeedbackForm $source, array $overrides = []): FeedbackForm
    {
        return DB::transaction(function () use ($source, $overrides): FeedbackForm {
            $source = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $source->id);
            $form = $this->createForm->execute(new CreateFeedbackFormData(
                name: $overrides['name'] ?? ($source->name . ' (Copy)'),
                purpose: $overrides['purpose'] ?? $source->purpose,
                status: 'draft',
                visibility: $source->visibility->value,
                isAnonymousAllowed: $source->is_anonymous_allowed,
                isAnonymityOptional: $source->is_anonymity_optional,
                isLoginRequired: $source->is_login_required,
                isOneResponsePerRespondent: $source->is_one_response_per_respondent,
                isEditAfterSubmitAllowed: $source->is_edit_after_submit_allowed,
                settings: $source->settings ?? [],
            ));

            $source->load('sections.questions.options');

            foreach ($source->sections as $section) {
                $newSection = $this->saveStructure->saveSection($form->id, [
                    'title' => $section->title,
                    'key' => $section->key,
                    'description' => $section->description,
                    'order_column' => $section->order_column,
                    'settings' => $section->settings ?? [],
                    'metadata' => $section->metadata ?? [],
                ]);

                foreach ($section->questions as $question) {
                    $newQuestion = $this->saveStructure->saveQuestion($form->id, [
                        'key' => $question->key,
                        'type' => $question->type,
                        'label' => $question->label,
                        'feedback_section_id' => $newSection->id,
                        'description' => $question->description,
                        'help_text' => $question->help_text,
                        'placeholder' => $question->placeholder,
                        'is_required' => $question->is_required,
                        'is_scored' => $question->is_scored,
                        'order_column' => $question->order_column,
                        'validation_rules' => $question->validation_rules ?? [],
                        'visibility_rules' => $question->visibility_rules ?? [],
                        'scoring_rules' => $question->scoring_rules ?? [],
                        'settings' => $question->settings ?? [],
                        'metadata' => $question->metadata ?? [],
                    ]);

                    foreach ($question->options as $option) {
                        $this->saveStructure->saveOption($newQuestion->id, [
                            'label' => $option->label,
                            'value' => $option->value,
                            'score' => $option->score,
                            'order_column' => $option->order_column,
                            'metadata' => $option->metadata ?? [],
                        ]);
                    }
                }
            }

            return $form->fresh();
        });
    }
}
