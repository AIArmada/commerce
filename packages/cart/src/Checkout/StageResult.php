<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout;

/**
 * Result of a checkout stage execution.
 */
final readonly class StageResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $errors
     */
    public function __construct(
        public bool $success,
        public string $message = '',
        public array $data = [],
        public array $errors = []
    ) {}

    /**
     * Create a successful result.
     *
     * @param  array<string, mixed>  $data
     */
    public static function success(string $message = '', array $data = []): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
        );
    }

    /**
     * Create a failed result.
     *
     * @param  array<string, string>  $errors
     */
    public static function failure(string $message, array $errors = []): self
    {
        return new self(
            success: false,
            message: $message,
            errors: $errors,
        );
    }
}
