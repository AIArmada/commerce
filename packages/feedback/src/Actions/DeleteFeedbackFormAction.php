<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackForm;

final class DeleteFeedbackFormAction
{
    public function execute(FeedbackForm $form): void
    {
        $form->sections()->delete();
        $form->questions()->delete();
        $form->responses()->delete();
        $form->invitations()->delete();
        $form->delete();
    }
}
