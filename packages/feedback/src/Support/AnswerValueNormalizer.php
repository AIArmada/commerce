<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Support;

use AIArmada\Feedback\Contracts\AnswerNormalizer;
use AIArmada\Feedback\Enums\FeedbackQuestionType;
use AIArmada\Feedback\Models\FeedbackQuestion;
use Carbon\CarbonImmutable;
use Throwable;

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
                'value' => $this->floatOrNull($value),
                'number_value' => $this->floatOrNull($value),
                'text_value' => $value !== null ? (string) $value : null,
            ],
            FeedbackQuestionType::Date => [
                'value' => $value,
                'date_value' => $this->dateStringOrNull($value),
            ],
            FeedbackQuestionType::DateTime => [
                'value' => $value,
                'datetime_value' => $this->dateTimeOrNull($value),
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
            FeedbackQuestionType::Matrix, FeedbackQuestionType::Likert => [
                'value' => $value,
                'text_value' => is_array($value)
                    ? implode(', ', array_map(strval(...), array_values($value)))
                    : (string) $value,
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
            'value' => $this->floatOrNull($value),
            'number_value' => $this->floatOrNull($value),
        ];
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function dateStringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function dateTimeOrNull(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function defaultNormalize(mixed $value): array
    {
        return [
            'value' => $value,
        ];
    }
}
