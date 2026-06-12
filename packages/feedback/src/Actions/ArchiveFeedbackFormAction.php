<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Models\FeedbackForm;
use Carbon\CarbonImmutable;

final class ArchiveFeedbackFormAction
{
    public function execute(FeedbackForm $form): FeedbackForm
    {
        if ($form->status === FeedbackFormStatus::Archived) {
            return $form;
        }

        $form->forceFill([
            'status' => FeedbackFormStatus::Archived,
            'archived_at' => CarbonImmutable::now(),
        ])->save();

        return $form;
    }
}
