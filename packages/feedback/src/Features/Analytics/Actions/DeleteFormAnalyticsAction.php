<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Analytics\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Analytics\Api\DeletesFormAnalytics;
use AIArmada\Feedback\Features\Analytics\Models\FeedbackFormAnalytics;
use AIArmada\Feedback\Models\FeedbackForm;

final readonly class DeleteFormAnalyticsAction implements DeletesFormAnalytics
{
    public function execute(string $formId): void
    {
        if (FeedbackForm::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $formId);
        } else {
            FeedbackForm::query()->whereKey($formId)->firstOrFail();
        }

        FeedbackFormAnalytics::query()->where('feedback_form_id', $formId)->delete();
    }
}
