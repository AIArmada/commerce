<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Analytics\Queries;

use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Responses\Api\ReadsResponseMetrics;

final readonly class CompletionRateCalculator
{
    public function __construct(private ReadsResponseMetrics $metrics) {}

    public function calculate(?FeedbackForm $form = null): float
    {
        return $this->metrics->summary($form?->id)->completionRate;
    }
}
