<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Events\FeedbackFormClosed;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class CloseFeedbackFormAction
{
    public function execute(FeedbackForm $form): FeedbackForm
    {
        return $form->getConnection()->transaction(function () use ($form): FeedbackForm {
            if (FeedbackForm::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);
            }

            $form = FeedbackForm::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();

            if ($form->status === FeedbackFormStatus::Archived) {
                throw new RuntimeException('Archived feedback forms cannot be closed.');
            }

            if ($form->status === FeedbackFormStatus::Closed) {
                return $form;
            }

            $form->forceFill([
                'status' => FeedbackFormStatus::Closed,
                'closed_at' => CarbonImmutable::now(),
            ])->save();

            FeedbackFormClosed::dispatch($form->id, $form->owner_type, $form->owner_id);

            return $form;
        });
    }
}
