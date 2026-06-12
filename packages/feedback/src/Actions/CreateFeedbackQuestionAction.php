<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackQuestion;

final class CreateFeedbackQuestionAction
{
    public function execute(
        string $formId,
        string $key,
        string $type,
        string $label,
        ?string $sectionId = null,
        ?string $description = null,
        ?string $helpText = null,
        ?string $placeholder = null,
        bool $isRequired = false,
        bool $isScored = false,
        int $orderColumn = 0,
        array $validationRules = [],
        array $visibilityRules = [],
        array $scoringRules = [],
        array $settings = [],
    ): FeedbackQuestion {
        return FeedbackQuestion::create([
            'feedback_form_id' => $formId,
            'feedback_section_id' => $sectionId,
            'key' => $key,
            'type' => $type,
            'label' => $label,
            'description' => $description,
            'help_text' => $helpText,
            'placeholder' => $placeholder,
            'is_required' => $isRequired,
            'is_scored' => $isScored,
            'order_column' => $orderColumn,
            'validation_rules' => $validationRules,
            'visibility_rules' => $visibilityRules,
            'scoring_rules' => $scoringRules,
            'settings' => $settings,
        ]);
    }
}
