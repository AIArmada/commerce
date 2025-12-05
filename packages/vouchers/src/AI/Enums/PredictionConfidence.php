<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Enums;

/**
 * Represents confidence levels for AI predictions.
 */
enum PredictionConfidence: string
{
    case VeryLow = 'very_low';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case VeryHigh = 'very_high';

    /**
     * Get the minimum confidence threshold for this level.
     */
    public function getMinThreshold(): float
    {
        return match ($this) {
            self::VeryLow => 0.0,
            self::Low => 0.2,
            self::Medium => 0.4,
            self::High => 0.6,
            self::VeryHigh => 0.8,
        };
    }

    /**
     * Get the maximum confidence threshold for this level.
     */
    public function getMaxThreshold(): float
    {
        return match ($this) {
            self::VeryLow => 0.2,
            self::Low => 0.4,
            self::Medium => 0.6,
            self::High => 0.8,
            self::VeryHigh => 1.0,
        };
    }

    /**
     * Get a human-readable label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::VeryLow => 'Very Low Confidence',
            self::Low => 'Low Confidence',
            self::Medium => 'Medium Confidence',
            self::High => 'High Confidence',
            self::VeryHigh => 'Very High Confidence',
        };
    }

    /**
     * Get the color for UI display.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::VeryLow => 'danger',
            self::Low => 'warning',
            self::Medium => 'info',
            self::High => 'success',
            self::VeryHigh => 'primary',
        };
    }

    /**
     * Check if predictions at this confidence level should be trusted.
     */
    public function isTrustworthy(): bool
    {
        return match ($this) {
            self::VeryLow, self::Low => false,
            self::Medium, self::High, self::VeryHigh => true,
        };
    }

    /**
     * Determine confidence level from a score.
     */
    public static function fromScore(float $score): self
    {
        return match (true) {
            $score >= 0.8 => self::VeryHigh,
            $score >= 0.6 => self::High,
            $score >= 0.4 => self::Medium,
            $score >= 0.2 => self::Low,
            default => self::VeryLow,
        };
    }
}
