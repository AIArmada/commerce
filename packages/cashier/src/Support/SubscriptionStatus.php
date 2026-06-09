<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case OnTrial = 'trialing';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case OnGracePeriod = 'grace_period';
    case Paused = 'paused';
    case Incomplete = 'incomplete';
    case Expired = 'expired';

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::OnTrial => 'warning',
            self::PastDue => 'danger',
            self::Canceled => 'danger',
            self::OnGracePeriod => 'info',
            self::Paused => 'gray',
            self::Incomplete => 'warning',
            self::Expired => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::OnTrial => 'heroicon-o-clock',
            self::PastDue => 'heroicon-o-exclamation-circle',
            self::Canceled => 'heroicon-o-x-circle',
            self::OnGracePeriod => 'heroicon-o-pause-circle',
            self::Paused => 'heroicon-o-pause',
            self::Incomplete => 'heroicon-o-question-mark-circle',
            self::Expired => 'heroicon-o-archive-box-x-mark',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => __('filament-cashier::subscriptions.status.active'),
            self::OnTrial => __('filament-cashier::subscriptions.status.on_trial'),
            self::PastDue => __('filament-cashier::subscriptions.status.past_due'),
            self::Canceled => __('filament-cashier::subscriptions.status.canceled'),
            self::OnGracePeriod => __('filament-cashier::subscriptions.status.grace_period'),
            self::Paused => __('filament-cashier::subscriptions.status.paused'),
            self::Incomplete => __('filament-cashier::subscriptions.status.incomplete'),
            self::Expired => __('filament-cashier::subscriptions.status.expired'),
        };
    }

    public function isCancelable(): bool
    {
        return in_array($this, [self::Active, self::OnTrial, self::PastDue]);
    }

    public function isResumable(): bool
    {
        return in_array($this, [self::OnGracePeriod, self::Paused]);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Active, self::OnTrial, self::OnGracePeriod]);
    }
}
