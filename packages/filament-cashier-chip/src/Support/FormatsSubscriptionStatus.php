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
    protected static function getStatusColor(SubscriptionStatus | string | null $status): string
    {
        return match (self::normalizeSubscriptionStatus($status)) {
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

    protected static function formatStatus(SubscriptionStatus | string | null $status): string
    {
        $normalized = self::normalizeSubscriptionStatus($status);

        if ($normalized === null) {
            return is_string($status) && $status !== ''
                ? ucfirst($status)
                : '—';
        }

        return match ($normalized) {
            SubscriptionStatus::Active => __('filament-cashier-chip::filament-cashier-chip.subscription.status.active'),
            SubscriptionStatus::Trialing => __('filament-cashier-chip::filament-cashier-chip.subscription.status.trialing'),
            SubscriptionStatus::Canceled => __('filament-cashier-chip::filament-cashier-chip.subscription.status.canceled'),
            SubscriptionStatus::PastDue => __('filament-cashier-chip::filament-cashier-chip.subscription.status.past_due'),
            SubscriptionStatus::Paused => __('filament-cashier-chip::filament-cashier-chip.subscription.status.paused'),
            SubscriptionStatus::Incomplete => __('filament-cashier-chip::filament-cashier-chip.subscription.status.incomplete'),
            SubscriptionStatus::IncompleteExpired => __('filament-cashier-chip::filament-cashier-chip.subscription.status.incomplete_expired'),
            SubscriptionStatus::Unpaid => __('filament-cashier-chip::filament-cashier-chip.subscription.status.unpaid'),
        };
    }

    private static function normalizeSubscriptionStatus(SubscriptionStatus | string | null $status): ?SubscriptionStatus
    {
        if ($status instanceof SubscriptionStatus) {
            return $status;
        }

        if ($status === null || $status === '') {
            return null;
        }

        return SubscriptionStatus::tryFrom($status);
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
