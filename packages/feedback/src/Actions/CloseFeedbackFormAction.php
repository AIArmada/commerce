<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Events\FeedbackFormClosed;
use AIArmada\Feedback\Models\FeedbackForm;
use Carbon\CarbonImmutable;

final class CloseFeedbackFormAction
{
    public function execute(FeedbackForm $form): FeedbackForm
    {
        $form = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);

        if ($form->status === FeedbackFormStatus::Closed) {
            return $form;
        }

        $form->forceFill([
            'status' => FeedbackFormStatus::Closed,
            'closed_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackFormClosed::dispatch($form);

        return $form;
    }
}
