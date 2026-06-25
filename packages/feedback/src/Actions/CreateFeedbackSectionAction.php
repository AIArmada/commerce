<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackSection;

final class CreateFeedbackSectionAction
{
    public function execute(
        string $formId,
        string $title,
        ?string $key = null,
        ?string $description = null,
        int $orderColumn = 0,
        array $settings = [],
    ): FeedbackSection {
        OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $formId);

        return FeedbackSection::create([
            'feedback_form_id' => $formId,
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'order_column' => $orderColumn,
            'settings' => $settings,
        ]);
    }
}
