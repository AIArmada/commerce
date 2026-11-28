<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use Carbon\CarbonImmutable;

final readonly class ArchiveFeedbackFormAction
{
    public function execute(FeedbackForm $form): FeedbackForm
    {
        return $form->getConnection()->transaction(function () use ($form): FeedbackForm {
            if (FeedbackForm::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);
            }

            $form = FeedbackForm::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();

            if ($form->status === FeedbackFormStatus::Archived) {
                return $form;
            }

            $form->forceFill([
                'status' => FeedbackFormStatus::Archived,
                'archived_at' => CarbonImmutable::now(),
            ])->save();

            return $form;
        });
    }
}
