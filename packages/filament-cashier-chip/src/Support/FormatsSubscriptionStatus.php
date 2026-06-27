<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Support;

use AIArmada\CashierChip\Enums\SubscriptionStatus;

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
        return match (SubscriptionStatus::tryFrom($status)) {
            SubscriptionStatus::Active => 'success',
            SubscriptionStatus::Trialing => 'warning',
            SubscriptionStatus::Canceled => 'danger',
            SubscriptionStatus::PastDue => 'danger',
            SubscriptionStatus::Paused => 'gray',
            SubscriptionStatus::Incomplete => 'warning',
            SubscriptionStatus::Unpaid => 'danger',
            default => 'gray',
        };
    }

    protected static function formatStatus(string $status): string
    {
        return match ($status) {
            SubscriptionStatus::Active->value => __('filament-cashier-chip::filament-cashier-chip.subscription.status.active'),
            SubscriptionStatus::Trialing->value => __('filament-cashier-chip::filament-cashier-chip.subscription.status.trialing'),
            SubscriptionStatus::Canceled->value => __('filament-cashier-chip::filament-cashier-chip.subscription.status.canceled'),
            SubscriptionStatus::PastDue->value => __('filament-cashier-chip::filament-cashier-chip.subscription.status.past_due'),
            SubscriptionStatus::Paused->value => __('filament-cashier-chip::filament-cashier-chip.subscription.status.paused'),
            SubscriptionStatus::Incomplete->value => __('filament-cashier-chip::filament-cashier-chip.subscription.status.incomplete'),
            SubscriptionStatus::IncompleteExpired->value => __('filament-cashier-chip::filament-cashier-chip.subscription.status.incomplete_expired'),
            SubscriptionStatus::Unpaid->value => __('filament-cashier-chip::filament-cashier-chip.subscription.status.unpaid'),
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
        $currency = config('cashier-chip.currency', 'MYR');
        $precision = (int) config('filament-cashier-chip.tables.amount_precision', 2);

        return mb_strtoupper($currency) . ' ' . number_format($amount / 100, $precision, '.', ',');
    }
}
