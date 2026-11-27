<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackSection;

final readonly class DeleteFeedbackSectionAction
{
    public function execute(FeedbackSection $section): void
    {
        $section->getConnection()->transaction(function () use ($section): void {
            if (FeedbackSection::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackSection::class, $section->id);
            }

            $section = FeedbackSection::query()->whereKey($section->id)->firstOrFail();
            FeedbackForm::query()->whereKey($section->feedback_form_id)->lockForUpdate()->firstOrFail();
            FeedbackSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail()->delete();
        });
    }
}
