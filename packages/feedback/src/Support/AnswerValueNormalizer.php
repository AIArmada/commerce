<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Support;

use AIArmada\Feedback\Contracts\AnswerNormalizer;
use AIArmada\Feedback\Enums\FeedbackQuestionType;
use AIArmada\Feedback\Models\FeedbackQuestion;
use Carbon\CarbonImmutable;

final class AnswerValueNormalizer implements AnswerNormalizer
{
    public function normalize(FeedbackQuestion $question, mixed $value): array
    {
        $type = FeedbackQuestionType::tryFrom($question->type);

        return match (true) {
            $type === null => $this->defaultNormalize($value),
            $type->isInputType() => $this->normalizeInput($type, $value),
            $type->isChoiceType() => $this->normalizeChoice($type, $value),
            $type->isScoredType() => $this->normalizeScored($value),
            $type->isDisplayOnly() => $this->defaultNormalize(null),
            default => $this->defaultNormalize($value),
        };
    }

    private function normalizeInput(FeedbackQuestionType $type, mixed $value): array
    {
        return match ($type) {
            FeedbackQuestionType::Number => [
                'value' => $value !== null ? (float) $value : null,
                'number_value' => $value !== null ? (float) $value : null,
                'text_value' => $value !== null ? (string) $value : null,
            ],
            FeedbackQuestionType::Date => [
                'value' => $value,
                'date_value' => $value,
            ],
            FeedbackQuestionType::DateTime => [
                'value' => $value,
                'datetime_value' => $value ? CarbonImmutable::parse($value) : null,
            ],
            FeedbackQuestionType::Email => [
                'value' => $value,
                'text_value' => $value,
            ],
            FeedbackQuestionType::Phone => [
                'value' => $value,
                'text_value' => $value,
            ],
            default => [
                'value' => $value,
                'text_value' => $value,
            ],
        };
    }

    private function normalizeChoice(FeedbackQuestionType $type, mixed $value): array
    {
        return match ($type) {
            FeedbackQuestionType::Boolean, FeedbackQuestionType::YesNo => [
                'value' => $value,
                'boolean_value' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ],
            FeedbackQuestionType::MultipleChoice, FeedbackQuestionType::Ranking => [
                'value' => is_array($value) ? $value : [$value],
                'text_value' => is_array($value) ? implode(', ', $value) : (string) $value,
            ],
            default => [
                'value' => $value,
                'text_value' => (string) $value,
            ],
        };
    }

    private function normalizeScored(mixed $value): array
    {
        return [
            'value' => $value !== null ? (float) $value : null,
            'number_value' => $value !== null ? (float) $value : null,
        ];
    }

    private function defaultNormalize(mixed $value): array
    {
        return [
            'value' => $value,
        ];
    }
}
