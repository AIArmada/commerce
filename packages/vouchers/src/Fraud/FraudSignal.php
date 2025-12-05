<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud;

use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;

/**
 * Value object representing a single detected fraud signal.
 *
 * @property-read FraudSignalType $type The type of fraud signal
 * @property-read float $score Severity score (0-100)
 * @property-read string $message Human-readable description
 * @property-read array<string, mixed> $metadata Additional context data
 */
final readonly class FraudSignal
{
    /**
     * @param  FraudSignalType  $type  The type of fraud signal
     * @param  float  $score  Severity score (0-100)
     * @param  string  $message  Human-readable description
     * @param  array<string, mixed>  $metadata  Additional context data
     */
    public function __construct(
        public FraudSignalType $type,
        public float $score,
        public string $message,
        public array $metadata = [],
    ) {}

    /**
     * Create a signal with default severity.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        FraudSignalType $type,
        string $message,
        array $metadata = [],
    ): self {
        return new self(
            type: $type,
            score: $type->getDefaultSeverity(),
            message: $message,
            metadata: $metadata,
        );
    }

    /**
     * Create a signal with custom severity.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function withScore(
        FraudSignalType $type,
        float $score,
        string $message,
        array $metadata = [],
    ): self {
        return new self(
            type: $type,
            score: max(0, min(100, $score)),
            message: $message,
            metadata: $metadata,
        );
    }

    /**
     * Get the category of this signal.
     */
    public function getCategory(): string
    {
        return $this->type->getCategory();
    }

    /**
     * Check if this is a high-severity signal.
     */
    public function isHighSeverity(): bool
    {
        return $this->score >= 50;
    }

    /**
     * Check if this is a critical-severity signal.
     */
    public function isCriticalSeverity(): bool
    {
        return $this->score >= 70;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->type->getLabel(),
            'category' => $this->getCategory(),
            'score' => $this->score,
            'message' => $this->message,
            'metadata' => $this->metadata,
        ];
    }
}
