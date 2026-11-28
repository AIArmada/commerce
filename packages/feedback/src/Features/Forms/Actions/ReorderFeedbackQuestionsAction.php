<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;

final readonly class ReorderFeedbackQuestionsAction
{
    /**
     * @param  array<string, int>  $order  [id => order_column]
     */
    public function execute(string $formId, array $order): void
    {
        (new FeedbackForm)->getConnection()->transaction(function () use ($formId, $order): void {
            if (FeedbackForm::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $formId);
            }

            FeedbackForm::query()->whereKey($formId)->lockForUpdate()->firstOrFail();

            foreach ($order as $id => $position) {
                FeedbackQuestion::where('feedback_form_id', $formId)
                    ->where('id', $id)
                    ->update(['order_column' => $position]);
            }
        });
    }
}
