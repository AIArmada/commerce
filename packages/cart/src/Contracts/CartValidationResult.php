<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

final readonly class CartValidationResult
{
    public function __construct(
        public bool $isValid,
        public ?string $message = null,
        public array $errors = [],
        public array $metadata = [],
    ) {}

    public static function valid(): self
    {
        return new self(isValid: true);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $metadata
     */
    public static function invalid(
        string $message,
        array $errors = [],
        array $metadata = [],
    ): self {
        return new self(
            isValid: false,
            message: $message,
            errors: $errors,
            metadata: $metadata,
        );
    }
}
