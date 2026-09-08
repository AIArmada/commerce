<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Support;

use AIArmada\Feedback\Enums\FeedbackQuestionType;

final class QuestionTypeRegistry
{
    public static function disabledTypes(): array
    {
        $disabledTypes = [];

        foreach (FeedbackQuestionType::cases() as $type) {
            if ($type->isDisabled()) {
                $disabledTypes[] = $type->value;
            }
        }

        return $disabledTypes;
    }

    public static function isTypeAvailable(string $type): bool
    {
        $enum = FeedbackQuestionType::tryFrom($type);

        if ($enum === null) {
            return false;
        }

        return ! $enum->isDisabled();
    }

    public static function availableTypes(): array
    {
        return array_values(array_filter(
            FeedbackQuestionType::options(),
            fn (string $value): bool => self::isTypeAvailable($value),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    public static function supportsChoices(string $type): bool
    {
        $enum = FeedbackQuestionType::tryFrom($type);

        return $enum?->isChoiceType() ?? false;
    }
}
