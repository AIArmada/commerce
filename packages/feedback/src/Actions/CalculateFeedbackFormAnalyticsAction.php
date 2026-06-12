<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Analytics\FeedbackAnalyticsService;
use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Models\FeedbackForm;

final class CalculateFeedbackFormAnalyticsAction
{
    public function __construct(
        private readonly FeedbackAnalyticsService $analytics,
    ) {}

    public function execute(FeedbackForm $form): FeedbackAnalyticsData
    {
        return $this->analytics->summaryForForm($form);
    }
}
