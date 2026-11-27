<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Analytics\Actions;

use AIArmada\Feedback\Features\Analytics\Api\DeletesFormAnalytics;
use AIArmada\Feedback\Features\Analytics\Models\FeedbackFormAnalytics;
use AIArmada\Feedback\Features\Forms\Api\ReadsOwnedFormIdentity;

final readonly class DeleteFormAnalyticsAction implements DeletesFormAnalytics
{
    public function __construct(private ReadsOwnedFormIdentity $forms) {}

    public function execute(string $formId): void
    {
        $this->forms->requireOwned($formId); else {
            FeedbackForm::query()->whereKey($formId)->firstOrFail();
        }

        FeedbackFormAnalytics::query()->where('feedback_form_id', $formId)->delete();
    }
}
