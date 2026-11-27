<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;

final readonly class DeleteFeedbackQuestionAction
{
    public function execute(FeedbackQuestion $question): void
    {
        $question->getConnection()->transaction(function () use ($question): void {
            if (FeedbackQuestion::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $question->id);
            }

            FeedbackQuestion::query()->whereKey($question->id)->lockForUpdate()->firstOrFail()->delete();
        });
    }
}
