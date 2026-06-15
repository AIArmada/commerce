<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

final readonly class PromoCodeValidationResult
{
    private function __construct(
        public bool $valid,
        public int $discount,
        public ?string $type,
        public ?string $label,
        public ?string $name,
        public ?string $error,
    ) {}

    public static function valid(int $discount, string $type, string $label, string $name): self
    {
        return new self(
            valid: true,
            discount: max(0, $discount),
            type: $type,
            label: $label,
            name: $name,
            error: null,
        );
    }

    public static function invalid(string $error): self
    {
        return new self(
            valid: false,
            discount: 0,
            type: null,
            label: null,
            name: null,
            error: $error,
        );
    }
}
