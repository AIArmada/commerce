<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Enums;

enum AuditSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Low => 'Routine operations, informational events',
            self::Medium => 'Notable changes that may require attention',
            self::High => 'Significant security-related events',
            self::Critical => 'Immediate attention required, potential security breach',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Low => 'heroicon-o-information-circle',
            self::Medium => 'heroicon-o-bell',
            self::High => 'heroicon-o-exclamation-triangle',
            self::Critical => 'heroicon-o-shield-exclamation',
        };
    }

    public function numericLevel(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    public function shouldNotify(): bool
    {
        return match ($this) {
            self::High, self::Critical => true,
            default => false,
        };
    }

    public function retentionDays(): int
    {
        return match ($this) {
            self::Low => 30,
            self::Medium => 90,
            self::High => 365,
            self::Critical => 730,
        };
    }
}
