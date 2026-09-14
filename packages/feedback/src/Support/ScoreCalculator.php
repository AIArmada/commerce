<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Support;

use AIArmada\Feedback\Enums\FeedbackQuestionType;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackQuestionOption;

final class ScoreCalculator
{
    public function calculateScore(FeedbackQuestion $question, mixed $value): ?float
    {
        $type = FeedbackQuestionType::tryFrom($question->type);

        if ($type === null) {
            return null;
        }

        if (! $question->is_scored && ! $type->isScoredType()) {
            return null;
        }

        if ($type->isScoredType()) {
            return $value !== null ? (float) $value : null;
        }

        if ($type->isChoiceType()) {
            return $this->calculateChoiceScore($question, $value);
        }

        $scoringRules = $question->scoring_rules;

        if (! empty($scoringRules) && isset($scoringRules['map'])) {
            return (float) ($scoringRules['map'][(string) $value] ?? 0);
        }

        return null;
    }

    public function calculateMaxScore(FeedbackQuestion $question): ?float
    {
        $type = FeedbackQuestionType::tryFrom($question->type);

        if ($type === null) {
            return null;
        }

        if ($question->is_scored && $type->isChoiceType()) {
            $max = $question->relationLoaded('options')
                ? $question->options->whereNotNull('score')->max('score')
                : $question->options()->max('score');

            return $max !== null ? (float) $max : null;
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

    private function calculateChoiceScore(FeedbackQuestion $question, mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $optionValues = is_array($value) ? $value : [$value];

        $options = $question->relationLoaded('options')
            ? $question->options
            : $question->options()->get();

        $scoresByValue = [];
        foreach ($options as $option) {
            /** @var FeedbackQuestionOption $option */
            if ($option->score !== null) {
                $scoresByValue[(string) $option->value] = (float) $option->score;
            }
        }

        $totalScore = 0.0;
        foreach ($optionValues as $val) {
            $totalScore += $scoresByValue[(string) $val] ?? 0.0;
        }

        return $totalScore;
    }
}
