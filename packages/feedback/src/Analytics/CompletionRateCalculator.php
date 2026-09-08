<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Analytics;

use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackResponse;

final class CompletionRateCalculator
{
    public function calculate(?FeedbackForm $form = null): float
    {
        $totalQuery = FeedbackResponse::query();

        if ($form !== null) {
            $totalQuery->where('feedback_form_id', $form->id);
        }

        $total = $totalQuery->count();

        if ($total === 0) {
            return 0.0;
        }

        $submittedQuery = FeedbackResponse::query()->where('status', 'submitted');

        if ($form !== null) {
            $submittedQuery->where('feedback_form_id', $form->id);
        }

        $submitted = $submittedQuery->count();

        return round(($submitted / $total) * 100, 2);
    }
}
