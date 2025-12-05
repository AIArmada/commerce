<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Enums;

enum PolicyDecision: string
{
    case Permit = 'permit';
    case Deny = 'deny';
    case NotApplicable = 'not_applicable';
    case Indeterminate = 'indeterminate';

    public function label(): string
    {
        return match ($this) {
            self::Permit => 'Permit',
            self::Deny => 'Deny',
            self::NotApplicable => 'Not Applicable',
            self::Indeterminate => 'Indeterminate',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Permit => 'Access is explicitly granted',
            self::Deny => 'Access is explicitly denied',
            self::NotApplicable => 'No applicable policy found for this request',
            self::Indeterminate => 'Policy evaluation could not complete (error or missing data)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Permit => 'success',
            self::Deny => 'danger',
            self::NotApplicable => 'gray',
            self::Indeterminate => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Permit => 'heroicon-o-check-circle',
            self::Deny => 'heroicon-o-x-circle',
            self::NotApplicable => 'heroicon-o-minus-circle',
            self::Indeterminate => 'heroicon-o-question-mark-circle',
        };
    }

    public function isAccessGranted(): bool
    {
        return $this === self::Permit;
    }

    public function isAccessDenied(): bool
    {
        return $this === self::Deny;
    }

    public function isConclusive(): bool
    {
        return $this === self::Permit || $this === self::Deny;
    }

    public function requiresFallback(): bool
    {
        return $this === self::NotApplicable || $this === self::Indeterminate;
    }
}
