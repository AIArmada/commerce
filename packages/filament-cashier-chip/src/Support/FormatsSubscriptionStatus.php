<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Support;

use AIArmada\CashierChip\Subscription;

/**
 * Shared subscription status formatting utilities.
 *
 * Centralizes status color and label formatting to eliminate
 * duplication across Tables and Infolists.
 */
trait FormatsSubscriptionStatus
{
    protected static function getStatusColor(string $status): string
    {
        return match ($status) {
            Subscription::STATUS_ACTIVE => 'success',
            Subscription::STATUS_TRIALING => 'warning',
            Subscription::STATUS_CANCELED => 'danger',
            Subscription::STATUS_PAST_DUE => 'danger',
            Subscription::STATUS_PAUSED => 'gray',
            Subscription::STATUS_INCOMPLETE => 'warning',
            Subscription::STATUS_UNPAID => 'danger',
            default => 'gray',
        };
    }

    protected static function formatStatus(string $status): string
    {
        return match ($status) {
            Subscription::STATUS_ACTIVE => __('filament-cashier-chip::filament-cashier-chip.subscription.status.active'),
            Subscription::STATUS_TRIALING => __('filament-cashier-chip::filament-cashier-chip.subscription.status.trialing'),
            Subscription::STATUS_CANCELED => __('filament-cashier-chip::filament-cashier-chip.subscription.status.canceled'),
            Subscription::STATUS_PAST_DUE => __('filament-cashier-chip::filament-cashier-chip.subscription.status.past_due'),
            Subscription::STATUS_PAUSED => __('filament-cashier-chip::filament-cashier-chip.subscription.status.paused'),
            Subscription::STATUS_INCOMPLETE => __('filament-cashier-chip::filament-cashier-chip.subscription.status.incomplete'),
            Subscription::STATUS_INCOMPLETE_EXPIRED => __('filament-cashier-chip::filament-cashier-chip.subscription.status.incomplete_expired'),
            Subscription::STATUS_UNPAID => __('filament-cashier-chip::filament-cashier-chip.subscription.status.unpaid'),
            default => ucfirst($status),
        };
    }

    protected static function formatInterval(?string $interval, ?int $count): string
    {
        if ($interval === null) {
            return '—';
        }

        $count = $count ?? 1;

        return match ($interval) {
            'day' => $count === 1 ? __('filament-cashier-chip::filament-cashier-chip.intervals.daily') : __('filament-cashier-chip::filament-cashier-chip.intervals.every_days', ['count' => $count]),
            'week' => $count === 1 ? __('filament-cashier-chip::filament-cashier-chip.intervals.weekly') : __('filament-cashier-chip::filament-cashier-chip.intervals.every_weeks', ['count' => $count]),
            'month' => $count === 1 ? __('filament-cashier-chip::filament-cashier-chip.intervals.monthly') : __('filament-cashier-chip::filament-cashier-chip.intervals.every_months', ['count' => $count]),
            'year' => $count === 1 ? __('filament-cashier-chip::filament-cashier-chip.intervals.yearly') : __('filament-cashier-chip::filament-cashier-chip.intervals.every_years', ['count' => $count]),
            default => "{$count} {$interval}",
        };
    }

    protected static function formatAmount(int $amount): string
    {
        $currency = config('filament-cashier-chip.currency', 'MYR');
        $value = $amount / 100;

        return mb_strtoupper($currency) . ' ' . number_format($value, 2, '.', ',');
    }
}
