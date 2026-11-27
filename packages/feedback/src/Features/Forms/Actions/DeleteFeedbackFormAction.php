<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;

final readonly class DeleteFeedbackFormAction
{
    public function execute(FeedbackForm $form): void
    {
        $form->getConnection()->transaction(function () use ($form): void {
            if (FeedbackForm::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);
            }

            FeedbackForm::query()->whereKey($form->id)->lockForUpdate()->firstOrFail()->delete();
        });
    }
}
