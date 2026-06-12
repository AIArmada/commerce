<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class ContactMethodData extends Data
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly string $type = '',
        public readonly string $purpose = 'general',
        public readonly string | null | Optional $label = null,
        public readonly string $value = '',
        public readonly string | null | Optional $normalizedValue = null,
        public readonly string | null | Optional $displayValue = null,
        public readonly string | null | Optional $countryCode = null,
        public readonly bool $isPrimary = false,
        public readonly bool $isPublic = true,
        public readonly bool $isVerified = false,
        public readonly mixed $verifiedAt = null,
        public readonly array $metadata = [],
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
}
