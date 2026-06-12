<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Support;

use AIArmada\Feedback\Enums\FeedbackQuestionType;
use AIArmada\Feedback\Models\FeedbackQuestion;

final class ValidationRuleBuilder
{
    public function build(FeedbackQuestion $question): array
    {
        $rules = [];
        $type = FeedbackQuestionType::tryFrom($question->type);
        $settings = $question->settings ?? [];

        if ($question->is_required && ! $type?->isDisplayOnly()) {
            $rules[] = 'required';
        } elseif (! $type?->isDisplayOnly()) {
            $rules[] = 'nullable';
        }

        if ($type === null) {
            return $rules;
        }

        return match (true) {
            $type->isInputType() => $this->inputRules($type, $question, $rules, $settings),
            $type->isChoiceType() => $this->choiceRules($type, $question, $rules),
            $type->isScoredType() => $this->scoredRules($type, $question, $rules, $settings),
            $type->isDisplayOnly() => ['nullable'],
            default => $rules,
        };
    }

    private function inputRules(FeedbackQuestionType $type, FeedbackQuestion $question, array $rules, array $settings): array
    {
        $rules[] = match ($type) {
            FeedbackQuestionType::ShortText, FeedbackQuestionType::LongText => 'string',
            FeedbackQuestionType::Email => 'email:rfc',
            FeedbackQuestionType::Phone => 'string',
            FeedbackQuestionType::Number => 'numeric',
            FeedbackQuestionType::Date => 'date',
            FeedbackQuestionType::Time => 'date_format:H:i',
            FeedbackQuestionType::DateTime => 'date',
            default => 'string',
        };

        if ($type === FeedbackQuestionType::ShortText && isset($settings['max_length'])) {
            $rules[] = 'max:' . (int) $settings['max_length'];
        }

        if ($type === FeedbackQuestionType::LongText && isset($settings['max_length'])) {
            $rules[] = 'max:' . (int) $settings['max_length'];
        }

        if (in_array($type, [FeedbackQuestionType::Number, FeedbackQuestionType::Rating, FeedbackQuestionType::Scale], true)) {
            if (isset($settings['min'])) {
                $rules[] = 'min:' . (float) $settings['min'];
            }
            if (isset($settings['max'])) {
                $rules[] = 'max:' . (float) $settings['max'];
            }
            if (! empty($settings['integer'])) {
                $rules[] = 'integer';
            }
        }

        return $rules;
    }

    private function choiceRules(FeedbackQuestionType $type, FeedbackQuestion $question, array $rules): array
    {
        if ($type === FeedbackQuestionType::MultipleChoice || $type === FeedbackQuestionType::Ranking) {
            $rules[] = 'array';
            if ($question->is_required) {
                $rules[] = 'min:1';
            }
        } elseif ($type === FeedbackQuestionType::Boolean || $type === FeedbackQuestionType::YesNo) {
            $rules[] = 'boolean';
        } else {
            $rules[] = 'string';
        }

        return $rules;
    }

    private function scoredRules(FeedbackQuestionType $type, FeedbackQuestion $question, array $rules, array $settings): array
    {
        $rules[] = 'numeric';

        $min = match ($type) {
            FeedbackQuestionType::Nps => 0,
            FeedbackQuestionType::Csat => 1,
            default => (int) ($settings['min'] ?? 0),
        };

        $max = match ($type) {
            FeedbackQuestionType::Nps => 10,
            FeedbackQuestionType::Csat => 5,
            default => (int) ($settings['max'] ?? 10),
        };

        $rules[] = "min:{$min}";
        $rules[] = "max:{$max}";

        return $rules;
    }
}
