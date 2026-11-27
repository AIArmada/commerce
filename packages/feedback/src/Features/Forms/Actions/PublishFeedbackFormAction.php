<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Events\FeedbackFormPublished;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use Carbon\CarbonImmutable;

final readonly class PublishFeedbackFormAction
{
    public function execute(FeedbackForm $form): FeedbackForm
    {
        return $form->getConnection()->transaction(function () use ($form): FeedbackForm {
            if (FeedbackForm::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);
            }

            $form = FeedbackForm::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();

            if ($form->status === FeedbackFormStatus::Published) {
                return $form;
            }

            $form->forceFill([
                'status' => FeedbackFormStatus::Published,
                'published_at' => CarbonImmutable::now(),
            ])->save();

            FeedbackFormPublished::dispatch($form->id, $form->owner_type, $form->owner_id);

            return $form;
        });
    }
}
