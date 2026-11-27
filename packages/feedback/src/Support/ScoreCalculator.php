<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Support;

use AIArmada\Feedback\Enums\FeedbackQuestionType;
use AIArmada\Feedback\Features\Forms\Api\QuestionDefinition;

final class ScoreCalculator
{
    public function calculateScore(QuestionDefinition $question, mixed $value): ?float
    {
        $type = FeedbackQuestionType::tryFrom($question->type);

        if ($type === null) {
            return null;
        }

        if (! $question->isScored && ! $type->isScoredType()) {
            return null;
        }

        if ($type->isScoredType()) {
            return $value !== null ? (float) $value : null;
        }

        if ($type->isChoiceType()) {
            return $this->calculateChoiceScore($question, $value);
        }

        $scoringRules = $question->scoringRules;

        if (! empty($scoringRules) && isset($scoringRules['map'])) {
            return (float) ($scoringRules['map'][(string) $value] ?? 0);
        }

        return null;
    }

    public function calculateMaxScore(QuestionDefinition $question): ?float
    {
        $type = FeedbackQuestionType::tryFrom($question->type);

        if ($type === null) {
            return null;
        }

        if ($question->isScored && $type->isChoiceType()) {
            $scores = array_filter(array_column($question->options, 'score'), static fn (?float $score): bool => $score !== null);

            return $scores === [] ? null : max($scores);
        }

        if ($type->isScoredType()) {
            $settings = $question->settings ?? [];

            return (float) match ($type) {
                FeedbackQuestionType::Nps => 10,
                FeedbackQuestionType::Csat => 5,
                default => $settings['max'] ?? 10,
            };
        }

        return null;
    }

    private function calculateChoiceScore(QuestionDefinition $question, mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $optionValues = is_array($value) ? $value : [$value];

        $scoresByValue = [];
        foreach ($question->options as $option) {
            if ($option['score'] !== null) {
                $scoresByValue[$option['value']] = $option['score'];
            }
        }

        $totalScore = 0.0;
        foreach ($optionValues as $val) {
            $totalScore += $scoresByValue[(string) $val] ?? 0.0;
        }

        return $totalScore;
    }
}
