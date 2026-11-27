<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Data;

use DateTimeInterface;

final readonly class ContactMethodData
{
    public function __construct(
        public ?string $id = null,
        public string $type = '',
        public string | MissingValue $purpose = MissingValue::Missing,
        public string | null | MissingValue $label = MissingValue::Missing,
        public string $value = '',
        public string | null | MissingValue $displayValue = MissingValue::Missing,
        public string | null | MissingValue $countryCode = MissingValue::Missing,
        public bool | MissingValue $isPrimary = MissingValue::Missing,
        public ?bool $isPublic = null,
        public bool | MissingValue $isVerified = MissingValue::Missing,
        public mixed $verifiedAt = null,
        public array | MissingValue $metadata = MissingValue::Missing,
        public DateTimeInterface | string | null $validFrom = null,
        public DateTimeInterface | string | null $validUntil = null,
        public ?int $sortOrder = null,
    ) {}

    public static function email(string $email, ?string $purpose = 'general', ?string $label = null): self
    {
        return new self(
            type: 'email',
            purpose: $purpose ?? 'general',
            label: $label,
            value: $email,
        );
    }

    public static function phone(string $phone, ?string $countryCode = null, ?string $purpose = 'general', ?string $label = null): self
    {
        return new self(
            type: 'phone',
            purpose: $purpose ?? 'general',
            label: $label,
            value: $phone,
            countryCode: $countryCode,
        );
    }

    public static function whatsapp(string $phone, ?string $countryCode = null, ?string $purpose = 'general', ?string $label = null): self
    {
        return new self(
            type: 'whatsapp',
            purpose: $purpose ?? 'general',
            label: $label,
            value: $phone,
            countryCode: $countryCode,
        );
    }

    public static function website(string $url, ?string $purpose = 'general', ?string $label = null): self
    {
        return new self(
            type: 'website',
            purpose: $purpose ?? 'general',
            label: $label,
            value: $url,
        );
    }

    /** @param array<string, mixed> $values */
    public static function from(array $values): self
    {
        $arguments = [];

        foreach (['id','type','purpose','label','value','displayValue','countryCode','isPrimary','isPublic','isVerified','verifiedAt','metadata','validFrom','validUntil','sortOrder'] as $key) {
            $snake = strtolower((string) preg_replace('/[A-Z]/', '_$0', $key));

            if (array_key_exists($snake, $values)) {
                $arguments[$key] = $values[$snake];
            } elseif (array_key_exists($key, $values)) {
                $arguments[$key] = $values[$key];
            }
        }

        return new self(...$arguments);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter(get_object_vars($this), static fn (mixed $value): bool => $value !== MissingValue::Missing);
    }
}
