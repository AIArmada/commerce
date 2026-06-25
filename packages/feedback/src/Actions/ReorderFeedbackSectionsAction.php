<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackSection;

final class ReorderFeedbackSectionsAction
{
    /**
     * @param  array<string, int>  $order  [id => order_column]
     */
    public function execute(string $formId, array $order): void
    {
        OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $formId);

        foreach ($order as $id => $position) {
            FeedbackSection::where('feedback_form_id', $formId)
                ->where('id', $id)
                ->update(['order_column' => $position]);
        }
    }
}
