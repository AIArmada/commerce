<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud\Enums;

/**
 * Risk levels for fraud analysis results.
 */
enum FraudRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Determine risk level from a fraud score.
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
     * Get all risk levels that should be blocked.
     *
     * @return array<FraudRiskLevel>
     */
    public static function blockingLevels(): array
    {
        return array_filter(
            self::cases(),
            fn (self $level): bool => $level->shouldBlock()
        );
    }

    /**
     * Get all risk levels that require review.
     *
     * @return array<FraudRiskLevel>
     */
    public static function reviewRequiredLevels(): array
    {
        return array_filter(
            self::cases(),
            fn (self $level): bool => $level->requiresReview()
        );
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
            self::Critical => 'danger',
        };
    }

    /**
     * Get the minimum score threshold for this risk level.
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
     * Get the maximum score threshold for this risk level.
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
     * Check if this risk level should block redemption by default.
     */
    public function shouldBlock(): bool
    {
        return match ($this) {
            self::Low, self::Medium => false,
            self::High, self::Critical => true,
        };
    }

    /**
     * Check if this risk level requires review.
     */
    public function requiresReview(): bool
    {
        return match ($this) {
            self::Low => false,
            self::Medium, self::High, self::Critical => true,
        };
    }

    /**
     * Get the recommended action for this risk level.
     */
    public function getRecommendedAction(): string
    {
        return match ($this) {
            self::Low => 'allow',
            self::Medium => 'flag_for_review',
            self::High => 'require_verification',
            self::Critical => 'block',
        };
    }

    /**
     * Check if a score falls within this risk level.
     */
    public function containsScore(float $score): bool
    {
        return $score >= $this->getMinScore() && $score < $this->getMaxScore();
    }
}
