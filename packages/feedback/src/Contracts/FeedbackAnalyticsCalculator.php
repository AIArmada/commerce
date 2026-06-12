<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Contracts;

use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Models\FeedbackForm;
use Illuminate\Support\Collection;

interface FeedbackAnalyticsCalculator
{
    public function summaryForForm(FeedbackForm $form): FeedbackAnalyticsData;

    public function averageForQuestion(FeedbackForm $form, string $questionKey): ?float;

    public function distributionForQuestion(FeedbackForm $form, string $questionKey): array;

    public function latestComments(FeedbackForm $form, int $limit = 10): Collection;

    public function completionRate(FeedbackForm $form): float;
}
