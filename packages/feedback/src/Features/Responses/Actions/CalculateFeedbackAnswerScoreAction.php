<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\Feedback\Features\Forms\Api\QuestionDefinition;
use AIArmada\Feedback\Support\ScoreCalculator;

final class CalculateFeedbackAnswerScoreAction
{
    public function __construct(
        private readonly ScoreCalculator $calculator,
    ) {}

    public function execute(QuestionDefinition $question, mixed $value): ?float
    {
        return $this->calculator->calculateScore($question, $value);
    }
}
