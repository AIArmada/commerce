<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Analytics;

use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackResponse;

final class CompletionRateCalculator
{
    public function calculate(FeedbackForm $form): float
    {
        $total = FeedbackResponse::query()
            ->where('feedback_form_id', $form->id)
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $submitted = FeedbackResponse::query()
            ->where('feedback_form_id', $form->id)
            ->where('status', 'submitted')
            ->count();

        return round(($submitted / $total) * 100, 2);
    }
}
