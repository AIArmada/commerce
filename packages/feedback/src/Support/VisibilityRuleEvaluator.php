<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Support;

use AIArmada\Feedback\Models\FeedbackQuestion;

final class VisibilityRuleEvaluator
{
    public function isVisible(FeedbackQuestion $question, array $answerValues): bool
    {
        $rules = $question->visibility_rules;

        if (empty($rules) || ! isset($rules['show_if'])) {
            return true;
        }

        $condition = $rules['show_if'];

        if (! isset($condition['question_key'], $condition['operator'])) {
            return true;
        }

        $actualValue = $answerValues[$condition['question_key']] ?? null;
        $expectedValue = $condition['value'] ?? null;

        return match ($condition['operator']) {
            '=' => $actualValue === $expectedValue,
            '!=' => $actualValue !== $expectedValue,
            '>' => $actualValue > $expectedValue,
            '>=' => $actualValue >= $expectedValue,
            '<' => $actualValue < $expectedValue,
            '<=' => $actualValue <= $expectedValue,
            'in' => is_array($actualValue) && in_array($expectedValue, $actualValue, true),
            'not_in' => ! is_array($actualValue) || ! in_array($expectedValue, $actualValue, true),
            'contains' => is_string($actualValue) && str_contains($actualValue, (string) $expectedValue),
            'not_contains' => ! is_string($actualValue) || ! str_contains($actualValue, (string) $expectedValue),
            'empty' => empty($actualValue),
            'not_empty' => ! empty($actualValue),
            default => true,
        };
    }

    public function filterHiddenQuestions(array $questions, array $answerValues): array
    {
        $visible = [];

        foreach ($questions as $question) {
            if ($this->isVisible($question, $answerValues)) {
                $visible[] = $question;
            }
        }

        return $visible;
    }
}
