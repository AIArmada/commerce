<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Enums;

enum ImpactLevel: string
{
    case None = 'none';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Calculate impact level from affected user count.
     */
    public static function fromAffectedUsers(int $count, int $totalUsers = 0): self
    {
        if ($count === 0) {
            return self::None;
        }

        if ($totalUsers > 0) {
            $percentage = ($count / $totalUsers) * 100;

            return match (true) {
                $percentage >= 75 => self::Critical,
                $percentage >= 50 => self::High,
                $percentage >= 25 => self::Medium,
                $percentage >= 5 => self::Low,
                default => self::None,
            };
        }

        return match (true) {
            $count >= 1000 => self::Critical,
            $count >= 100 => self::High,
            $count >= 10 => self::Medium,
            $count >= 1 => self::Low,
            default => self::None,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'No Impact',
            self::Low => 'Low Impact',
            self::Medium => 'Medium Impact',
            self::High => 'High Impact',
            self::Critical => 'Critical Impact',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::None => 'No users or functionality affected',
            self::Low => 'Minimal impact, affects few users or non-critical features',
            self::Medium => 'Moderate impact, affects some users or important features',
            self::High => 'Significant impact, affects many users or critical features',
            self::Critical => 'Severe impact, system-wide or security-critical changes',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Low => 'success',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::None => 'heroicon-o-check-circle',
            self::Low => 'heroicon-o-information-circle',
            self::Medium => 'heroicon-o-exclamation-circle',
            self::High => 'heroicon-o-exclamation-triangle',
            self::Critical => 'heroicon-o-shield-exclamation',
        };
    }

    public function numericLevel(): int
    {
        return match ($this) {
            self::None => 0,
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    public function requiresApproval(): bool
    {
        return match ($this) {
            self::High, self::Critical => true,
            default => false,
        };
    }

    public function requiresConfirmation(): bool
    {
        return match ($this) {
            self::Medium, self::High, self::Critical => true,
            default => false,
        };
    }
}
