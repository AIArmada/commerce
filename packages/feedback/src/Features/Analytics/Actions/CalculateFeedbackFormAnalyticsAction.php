<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Analytics\Actions;

use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Features\Analytics\Queries\FeedbackAnalyticsQuery;
use AIArmada\Feedback\Models\FeedbackForm;

final class CalculateFeedbackFormAnalyticsAction
{
    public function __construct(
        private readonly FeedbackAnalyticsQuery $analytics,
    ) {}

    public function execute(FeedbackForm $form): FeedbackAnalyticsData
    {
        return $this->analytics->calculateLive($form);
    }
}
