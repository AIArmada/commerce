<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Api;

use AIArmada\Feedback\Data\FeedbackAnalyticsData;

interface ReadsResponseMetrics
{
    public function summary(?string $formId = null): FeedbackAnalyticsData;

    /** @return array<string, mixed> */
    public function dashboardMetrics(): array;
}
