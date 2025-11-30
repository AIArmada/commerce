<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Database\Factories;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Subscription;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $model = Cashier::$customerModel;

        return [
            (new $model)->getForeignKey() => ($model)::factory(),
            'type' => 'default',
            'chip_id' => 'sub_'.Str::random(40),
            'chip_status' => Subscription::STATUS_ACTIVE,
            'chip_price' => null,
            'quantity' => null,
            'trial_ends_at' => null,
            'ends_at' => null,
            'next_billing_at' => Carbon::now()->addMonth(),
            'billing_interval' => 'month',
            'recurring_token' => 'tok_'.Str::random(32),
        ];
    }

    /**
     * Add a price identifier to the model.
     */
    public function withPrice(string $price): static
    {
        return $this->state([
            'chip_price' => $price,
        ]);
    }

    /**
     * Add a specific billing interval.
     */
    public function billingInterval(string $interval): static
    {
        $nextBillingAt = match ($interval) {
            'day' => Carbon::now()->addDay(),
            'week' => Carbon::now()->addWeek(),
            'month' => Carbon::now()->addMonth(),
            'year' => Carbon::now()->addYear(),
            default => Carbon::now()->addMonth(),
        };

        return $this->state([
            'billing_interval' => $interval,
            'next_billing_at' => $nextBillingAt,
        ]);
    }

    /**
     * Configure a monthly subscription.
     */
    public function monthly(): static
    {
        return $this->billingInterval('month');
    }

    /**
     * Configure a yearly subscription.
     */
    public function yearly(): static
    {
        return $this->billingInterval('year');
    }

    /**
     * Configure a weekly subscription.
     */
    public function weekly(): static
    {
        return $this->billingInterval('week');
    }

    /**
     * Configure a daily subscription.
     */
    public function daily(): static
    {
        return $this->billingInterval('day');
    }

    /**
     * Mark the subscription as active.
     */
    public function active(): static
    {
        return $this->state([
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    /**
     * Mark the subscription as being within a trial period.
     */
    public function trialing(?DateTimeInterface $trialEndsAt = null): static
    {
        return $this->state([
            'chip_status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => $trialEndsAt ?? Carbon::now()->addDays(14),
        ]);
    }

    /**
     * Mark the subscription as canceled.
     */
    public function canceled(): static
    {
        return $this->state([
            'chip_status' => Subscription::STATUS_CANCELED,
            'ends_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark the subscription as on grace period (canceled but not yet ended).
     */
    public function onGracePeriod(): static
    {
        return $this->state([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'ends_at' => Carbon::now()->addDays(7),
        ]);
    }

    /**
     * Mark the subscription as incomplete.
     */
    public function incomplete(): static
    {
        return $this->state([
            'chip_status' => Subscription::STATUS_INCOMPLETE,
        ]);
    }

    /**
     * Mark the subscription as incomplete where the allowed completion period has expired.
     */
    public function incompleteAndExpired(): static
    {
        return $this->state([
            'chip_status' => Subscription::STATUS_INCOMPLETE_EXPIRED,
        ]);
    }

    /**
     * Mark the subscription as being past the due date.
     */
    public function pastDue(): static
    {
        return $this->state([
            'chip_status' => Subscription::STATUS_PAST_DUE,
        ]);
    }

    /**
     * Mark the subscription as unpaid.
     */
    public function unpaid(): static
    {
        return $this->state([
            'chip_status' => Subscription::STATUS_UNPAID,
        ]);
    }

    /**
     * Mark the subscription as paused.
     */
    public function paused(): static
    {
        return $this->state([
            'chip_status' => Subscription::STATUS_PAUSED,
        ]);
    }

    /**
     * Set a specific recurring token.
     */
    public function withRecurringToken(string $token): static
    {
        return $this->state([
            'recurring_token' => $token,
        ]);
    }

    /**
     * Set a specific subscription type.
     */
    public function type(string $type): static
    {
        return $this->state([
            'type' => $type,
        ]);
    }
}
