<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Data;

use JsonSerializable;

/**
 * Result of voucher validation.
 *
 * Represents whether a voucher is valid for use and if not, the reason why.
 */
final readonly class VoucherValidationResult implements JsonSerializable
{
    /**
     * @param  array<string, mixed>|null  $details  Additional details about the validation result
     */
    public function __construct(
        public bool $isValid,
        public ?string $reason = null,
        public ?array $details = null,
    ) {}

    /**
     * Create a successful validation result.
     */
    public static function valid(): self
    {
        return new self(isValid: true);
    }

    /**
     * Create a failed validation result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function invalid(string $reason, array $details = []): self
    {
        return new self(
            isValid: false,
            reason: $reason,
            details: $details !== [] ? $details : null
        );
    }

    /**
     * Check if the validation passed.
     */
    public function passed(): bool
    {
        return $this->isValid;
    }

    /**
     * Check if the validation failed.
     */
    public function failed(): bool
    {
        return ! $this->isValid;
    }

    /**
     * Get the failure reason if any.
     */
    public function getFailureReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Get a detail value by key.
     */
    public function getDetail(string $key, mixed $default = null): mixed
    {
        return $this->details[$key] ?? $default;
    }

    /** @return array{is_valid: bool, reason: ?string, details: ?array} */
    public function toArray(): array
    {
        return ['is_valid' => $this->isValid, 'reason' => $this->reason, 'details' => $this->details];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }
}
