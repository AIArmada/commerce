<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

enum FraudSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 100 => self::Critical,
            $score >= 80 => self::High,
            $score >= 50 => self::Medium,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'warning',
            self::High => 'danger',
            self::Critical => 'danger',
        };
    }

    public function riskThreshold(): int
    {
        return match ($this) {
            self::Low => 20,
            self::Medium => 50,
            self::High => 80,
            self::Critical => 100,
        };
    }
}
