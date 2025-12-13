<?php

declare(strict_types=1);

namespace AIArmada\Products\Enums;

use DateTimeImmutable;
use DateTimeInterface;

enum AttributeType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Boolean = 'boolean';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Date = 'date';
    case Color = 'color';
    case Media = 'media';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('products::enums.attribute_type.text'),
            self::Textarea => __('products::enums.attribute_type.textarea'),
            self::Number => __('products::enums.attribute_type.number'),
            self::Boolean => __('products::enums.attribute_type.boolean'),
            self::Select => __('products::enums.attribute_type.select'),
            self::Multiselect => __('products::enums.attribute_type.multiselect'),
            self::Date => __('products::enums.attribute_type.date'),
            self::Color => __('products::enums.attribute_type.color'),
            self::Media => __('products::enums.attribute_type.media'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Text => 'heroicon-o-pencil',
            self::Textarea => 'heroicon-o-document-text',
            self::Number => 'heroicon-o-hashtag',
            self::Boolean => 'heroicon-o-check-circle',
            self::Select => 'heroicon-o-chevron-down',
            self::Multiselect => 'heroicon-o-list-bullet',
            self::Date => 'heroicon-o-calendar',
            self::Color => 'heroicon-o-swatch',
            self::Media => 'heroicon-o-photo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Text => 'gray',
            self::Textarea => 'gray',
            self::Number => 'info',
            self::Boolean => 'success',
            self::Select => 'warning',
            self::Multiselect => 'warning',
            self::Date => 'primary',
            self::Color => 'danger',
            self::Media => 'info',
        };
    }

    /**
     * Check if this type supports options (for select/multiselect).
     */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::Multiselect], true);
    }

    /**
     * Check if this type stores multiple values.
     */
    public function isMultiple(): bool
    {
        return $this === self::Multiselect;
    }

    /**
     * Get the default validation rules for this type.
     *
     * @return array<string>
     */
    public function defaultValidation(): array
    {
        return match ($this) {
            self::Text => ['string', 'max:255'],
            self::Textarea => ['string', 'max:65535'],
            self::Number => ['numeric'],
            self::Boolean => ['boolean'],
            self::Select => ['string'],
            self::Multiselect => ['array'],
            self::Date => ['date'],
            self::Color => ['string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            self::Media => ['string'],
        };
    }

    /**
     * Cast a raw value to the appropriate PHP type.
     */
    public function castValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::Text, self::Textarea, self::Select, self::Color, self::Media => (string) $value,
            self::Number => is_numeric($value) ? (float) $value : null,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::Multiselect => is_array($value) ? $value : json_decode((string) $value, true),
            self::Date => $value instanceof DateTimeInterface ? $value : new DateTimeImmutable((string) $value),
        };
    }

    /**
     * Serialize a value for storage.
     */
    public function serializeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::Text, self::Textarea, self::Select, self::Color, self::Media => (string) $value,
            self::Number => (string) $value,
            self::Boolean => $value ? '1' : '0',
            self::Multiselect => json_encode($value),
            self::Date => $value instanceof DateTimeInterface
                ? $value->format('Y-m-d')
                : (string) $value,
        };
    }
}
