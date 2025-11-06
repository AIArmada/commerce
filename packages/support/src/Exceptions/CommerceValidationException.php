<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Exceptions;

use Throwable;

/**
 * Exception for validation failures across commerce packages.
 *
 * This exception is thrown when data validation fails, whether it's user input,
 * API responses, or internal data integrity checks.
 */
class CommerceValidationException extends CommerceException
{
    /**
     * Create a new validation exception.
     *
     * @param  string  $message  The exception message
     * @param  array<string, mixed>  $errors  Validation errors keyed by field name
     * @param  string|null  $errorCode  Optional error code
     * @param  Throwable|null  $previous  The previous throwable used for exception chaining
     */
    public function __construct(
        string $message,
        protected array $errors = [],
        ?string $errorCode = 'validation_failed',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, ['errors' => $errors], 422, $previous);
    }

    /**
     * Create a validation exception for a single field.
     */
    public static function forField(string $field, string $error, ?string $errorCode = null): static
    {
        return new static( // @phpstan-ignore new.static
            message: "Validation failed for field: {$field}",
            errors: [$field => [$error]],
            errorCode: $errorCode
        );
    }

    /**
     * Get validation errors.
     *
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if a specific field has validation errors.
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get errors for a specific field.
     *
     * @return array<int, string>|null
     */
    public function getFieldErrors(string $field): ?array
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): array
    {
        return array_merge(parent::getContext(), [
            'validation_errors' => $this->errors,
            'failed_fields' => array_keys($this->errors),
        ]);
    }
}
