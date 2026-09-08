<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackTemplate;
use Illuminate\Support\Facades\DB;

final class CreateFeedbackFormFromTemplateAction
{
    public function __construct(
        private readonly CreateFeedbackFormAction $createForm,
        private readonly SaveFeedbackFormStructureAction $saveStructure,
    ) {}

    public function execute(FeedbackTemplate $template, array $overrides = []): FeedbackForm
    {
        return DB::transaction(function () use ($template, $overrides): FeedbackForm {
            $template = OwnerWriteGuard::findOrFailForOwner(
                FeedbackTemplate::class,
                $template->id,
                includeGlobal: (bool) config('feedback.owner.include_global_templates', false),
            );
            $definition = $template->definition ?? [];

            $form = $this->createForm->execute(new CreateFeedbackFormData(
                name: $overrides['name'] ?? $template->name,
                slug: $overrides['slug'] ?? null,
                purpose: $overrides['purpose'] ?? $template->purpose,
                status: $overrides['status'] ?? 'draft',
                visibility: $overrides['visibility'] ?? 'private',
                subjectType: $overrides['subject_type'] ?? null,
                subjectId: $overrides['subject_id'] ?? null,
                settings: $overrides['settings'] ?? $template->settings ?? [],
            ));

            $sections = $definition['sections'] ?? [];

            foreach ($sections as $sectionData) {
                $section = $this->saveStructure->saveSection($form->id, [
                    'title' => $sectionData['title'] ?? 'Section',
                    'key' => $sectionData['key'] ?? null,
                    'description' => $sectionData['description'] ?? null,
                    'order_column' => $sectionData['order_column'] ?? 0,
                    'settings' => $sectionData['settings'] ?? [],
                    'metadata' => $sectionData['metadata'] ?? [],
                ]);

                $questions = $sectionData['questions'] ?? [];
                foreach ($questions as $questionData) {
                    $question = $this->saveStructure->saveQuestion($form->id, [
                        'key' => $questionData['key'],
                        'type' => $questionData['type'],
                        'label' => $questionData['label'],
                        'feedback_section_id' => $section->id,
                        'description' => $questionData['description'] ?? null,
                        'help_text' => $questionData['help_text'] ?? null,
                        'placeholder' => $questionData['placeholder'] ?? null,
                        'is_required' => $questionData['is_required'] ?? false,
                        'is_scored' => $questionData['is_scored'] ?? false,
                        'order_column' => $questionData['order_column'] ?? 0,
                        'validation_rules' => $questionData['validation_rules'] ?? [],
                        'visibility_rules' => $questionData['visibility_rules'] ?? [],
                        'scoring_rules' => $questionData['scoring_rules'] ?? [],
                        'settings' => $questionData['settings'] ?? [],
                        'metadata' => $questionData['metadata'] ?? [],
                    ]);

                    $options = $questionData['options'] ?? [];
                    foreach ($options as $optionData) {
                        $this->saveStructure->saveOption($question->id, [
                            'label' => $optionData['label'],
                            'value' => $optionData['value'],
                            'score' => $optionData['score'] ?? null,
                            'order_column' => $optionData['order_column'] ?? 0,
                            'metadata' => $optionData['metadata'] ?? [],
                        ]);
                    }
                }
            }

            return $form->fresh();
        });
    }
}
