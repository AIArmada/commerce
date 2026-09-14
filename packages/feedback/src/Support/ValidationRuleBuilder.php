<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Support;

use AIArmada\Feedback\Enums\FeedbackQuestionType;
use AIArmada\Feedback\Models\FeedbackQuestion;
use Closure;
use Illuminate\Validation\Rule;

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

        if ($type->isDisabled()) {
            $rules[] = $this->disabledTypeRule($type);

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
            $rules[] = $this->optionMembershipRule($question, true);

            return $rules;
        }

        if ($type === FeedbackQuestionType::Boolean || $type === FeedbackQuestionType::YesNo) {
            $rules[] = 'boolean';

            return $rules;
        }

        if ($type === FeedbackQuestionType::Matrix || $type === FeedbackQuestionType::Likert) {
            // Matrix/Likert payloads may be a single selected value or a per-row map.
            $rules[] = $this->optionMembershipRule($question, false);

            return $rules;
        }

        $rules[] = 'string';

        $allowed = $this->allowedOptionValues($question);

        if ($allowed !== []) {
            $rules[] = Rule::in($allowed);
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

    private function disabledTypeRule(FeedbackQuestionType $type): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($type): void {
            $fail("The {$type->value} question type is not available.");
        };
    }

    private function optionMembershipRule(FeedbackQuestion $question, bool $requireArray): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($question, $requireArray): void {
            $allowed = $this->allowedOptionValues($question);

            if ($allowed === []) {
                return;
            }

            if ($requireArray && ! is_array($value)) {
                return;
            }

            $values = is_array($value) ? array_values($value) : [$value];

            foreach ($values as $entry) {
                if (! is_scalar($entry) || ! in_array((string) $entry, $allowed, true)) {
                    $fail('The selected value is invalid.');

                    return;
                }
            }
        };
    }

    /**
     * @return list<string>
     */
    private function allowedOptionValues(FeedbackQuestion $question): array
    {
        $options = $question->relationLoaded('options')
            ? $question->options
            : $question->options()->get();

        return $options
            ->map(fn ($option): string => (string) $option->value)
            ->values()
            ->all();
    }
}
