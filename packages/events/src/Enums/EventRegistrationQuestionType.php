<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum EventRegistrationQuestionType: string
{
    use HasLabelOptions;

    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Checkbox = 'checkbox';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Short text',
            self::Textarea => 'Long text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Select => 'Single choice',
            self::Multiselect => 'Multiple choice',
            self::Checkbox => 'Checkbox',
        };
    }

    public function requiresOptions(): bool
    {
        return in_array($this, [self::Select, self::Multiselect], true);
    }

    public function acceptsMultipleValues(): bool
    {
        return $this === self::Multiselect;
    }
}
