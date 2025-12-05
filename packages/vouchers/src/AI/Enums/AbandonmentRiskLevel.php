<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Enums;

/**
 * Represents cart abandonment risk levels.
 */
enum AbandonmentRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Determine risk level from a score.
     */
    public static function fromScore(float $score): self
    {
        return match (true) {
            $score >= 0.8 => self::Critical,
            $score >= 0.6 => self::High,
            $score >= 0.3 => self::Medium,
            default => self::Low,
        };
    }

    /**
     * Get the minimum risk score for this level.
     */
    public function getMinScore(): float
    {
        return match ($this) {
            self::Low => 0.0,
            self::Medium => 0.3,
            self::High => 0.6,
            self::Critical => 0.8,
        };
    }

    /**
     * Get the maximum risk score for this level.
     */
    public function getMaxScore(): float
    {
        return match ($this) {
            self::Low => 0.3,
            self::Medium => 0.6,
            self::High => 0.8,
            self::Critical => 1.0,
        };
    }

    /**
     * Get a human-readable label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Low Risk',
            self::Medium => 'Medium Risk',
            self::High => 'High Risk',
            self::Critical => 'Critical Risk',
        };
    }

    /**
     * Get the color for UI display.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'success',
            self::Medium => 'warning',
            self::High => 'danger',
            self::Critical => 'gray',
        };
    }

    /**
     * Get the recommended intervention for this risk level.
     */
    public function getRecommendedIntervention(): string
    {
        return match ($this) {
            self::Low => 'none',
            self::Medium => 'exit_popup',
            self::High => 'discount_offer',
            self::Critical => 'recovery_email',
        };
    }

    /**
     * Check if this risk level requires immediate action.
     */
    public function requiresImmediateAction(): bool
    {
        return match ($this) {
            self::Low, self::Medium => false,
            self::High, self::Critical => true,
        };
    }

    /**
     * Get urgency weight for prioritization.
     */
    public function getUrgencyWeight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 4,
            self::Critical => 8,
        };
    }
}
