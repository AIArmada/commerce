<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Campaigns\Enums;

/**
 * Campaign lifecycle statuses.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'info',
            self::Active => 'success',
            self::Paused => 'warning',
            self::Completed => 'primary',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Check if campaign can receive traffic.
     */
    public function canReceiveTraffic(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if campaign can be edited.
     */
    public function canBeEdited(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::Paused], true);
    }

    /**
     * Check if campaign is terminal (no further transitions).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * Get allowed transitions from current status.
     *
     * @return array<CampaignStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Active, self::Cancelled],
            self::Scheduled => [self::Active, self::Paused, self::Cancelled],
            self::Active => [self::Paused, self::Completed, self::Cancelled],
            self::Paused => [self::Active, self::Completed, self::Cancelled],
            self::Completed => [],
            self::Cancelled => [],
        };
    }

    /**
     * Check if can transition to given status.
     */
    public function canTransitionTo(CampaignStatus $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Get options for UI dropdowns.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
