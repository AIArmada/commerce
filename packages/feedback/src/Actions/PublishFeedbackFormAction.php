<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Events\FeedbackFormPublished;
use AIArmada\Feedback\Models\FeedbackForm;
use Carbon\CarbonImmutable;

final class PublishFeedbackFormAction
{
    public function execute(FeedbackForm $form): FeedbackForm
    {
        if ($form->status === FeedbackFormStatus::Published) {
            return $form;
        }

        $form->forceFill([
            'status' => FeedbackFormStatus::Published,
            'published_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackFormPublished::dispatch($form);

        return $form;
    }
}
