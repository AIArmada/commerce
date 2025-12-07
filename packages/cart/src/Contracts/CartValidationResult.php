<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

/**
 * Result of cart validation.
 */
final readonly class CartValidationResult
{
    /**
     * @param  array<string, string>  $errors  Map of item ID => error message
     * @param  array<string, mixed>  $metadata  Additional validation metadata
     */
    public function __construct(
        public bool $isValid,
        public ?string $message = null,
        public array $errors = [],
        public array $metadata = []
    ) {}

    /**
     * Create a valid result.
     */
    public static function valid(): self
    {
        return new self(isValid: true);
    }

    /**
     * Create an invalid result with a message.
     *
     * @param  array<string, string>  $errors
     * @param  array<string, mixed>  $metadata
     */
    public static function invalid(string $message, array $errors = [], array $metadata = []): self
    {
        return new self(
            isValid: false,
            message: $message,
            errors: $errors,
            metadata: $metadata
        );
    }

    /**
     * Check if there are any item-level errors.
     */
    public function hasItemErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Get error for a specific item.
     */
    public function getItemError(string $itemId): ?string
    {
        return $this->errors[$itemId] ?? null;
    }
}
