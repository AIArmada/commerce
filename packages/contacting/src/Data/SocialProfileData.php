<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Data;

use DateTimeInterface;

final readonly class SocialProfileData
{
    public function __construct(
        public ?string $id = null,
        public string $platform = '',
        public string | MissingValue $purpose = MissingValue::Missing,
        public string | null | MissingValue $label = MissingValue::Missing,
        public string | null | MissingValue $handle = MissingValue::Missing,
        public string | null | MissingValue $url = MissingValue::Missing,
        public string | null | MissingValue $displayName = MissingValue::Missing,
        public string | null | MissingValue $externalId = MissingValue::Missing,
        public bool | MissingValue $isPrimary = MissingValue::Missing,
        public ?bool $isPublic = null,
        public bool | MissingValue $isVerified = MissingValue::Missing,
        public mixed $verifiedAt = null,
        public array | MissingValue $metadata = MissingValue::Missing,
        public DateTimeInterface | string | null $validFrom = null,
        public DateTimeInterface | string | null $validUntil = null,
        public ?int $sortOrder = null,
    ) {}

    /** @param array<string, mixed> $values */
    public static function from(array $values): self
    {
        $arguments = [];

        foreach (['id','platform','purpose','label','handle','url','displayName','externalId','isPrimary','isPublic','isVerified','verifiedAt','metadata','validFrom','validUntil','sortOrder'] as $key) {
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
