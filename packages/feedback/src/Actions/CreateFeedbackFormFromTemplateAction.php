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
        private readonly CreateFeedbackSectionAction $createSection,
        private readonly CreateFeedbackQuestionAction $createQuestion,
        private readonly CreateFeedbackQuestionOptionAction $createOption,
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
                $section = $this->createSection->execute(
                    $form->id,
                    $sectionData['title'] ?? 'Section',
                    $sectionData['key'] ?? null,
                );

                $questions = $sectionData['questions'] ?? [];
                foreach ($questions as $questionData) {
                    $question = $this->createQuestion->execute(
                        formId: $form->id,
                        key: $questionData['key'],
                        type: $questionData['type'],
                        label: $questionData['label'],
                        sectionId: $section->id,
                        isRequired: $questionData['is_required'] ?? false,
                        settings: $questionData['settings'] ?? [],
                    );

                    $options = $questionData['options'] ?? [];
                    foreach ($options as $optionData) {
                        $this->createOption->execute(
                            questionId: $question->id,
                            label: $optionData['label'],
                            value: $optionData['value'],
                            score: $optionData['score'] ?? null,
                        );
                    }
                }
            }

            return $form->fresh();
        });
    }
}
