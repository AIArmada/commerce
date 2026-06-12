<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Support\ScoreCalculator;

final class CalculateFeedbackAnswerScoreAction
{
    public function __construct(
        private readonly ScoreCalculator $calculator,
    ) {}

    public function execute(FeedbackQuestion $question, mixed $value): ?float
    {
        return $this->calculator->calculateScore($question, $value);
    }
}
