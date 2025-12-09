<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;

/**
 * Contract for subscription wrappers.
 *
 * This contract defines the interface for subscription wrappers that adapt
 * underlying gateway subscriptions (Laravel Cashier, CashierChip) to a
 * unified interface.
 *
 * @extends Arrayable<string, mixed>
 */
interface SubscriptionContract extends Arrayable, Jsonable
{
    /**
     * Get the subscription ID (local database ID).
     */
    public function id(): string;

    /**
     * Get the subscription's gateway name.
     */
    public function gateway(): string;

    /**
     * Get the subscription's gateway ID (e.g., Stripe subscription ID).
     */
    public function gatewayId(): string;

    /**
     * Get the subscription type.
     */
    public function type(): string;

    /**
     * Determine if the subscription is active.
     */
    public function active(): bool;

    /**
     * Determine if the subscription is valid (active or on grace period).
     */
    public function valid(): bool;

    /**
     * Determine if the subscription is on trial.
     */
    public function onTrial(): bool;

    /**
     * Determine if the trial has expired.
     */
    public function hasExpiredTrial(): bool;

    /**
     * Determine if the subscription is canceled.
     */
    public function canceled(): bool;

    /**
     * Determine if the subscription is on grace period.
     */
    public function onGracePeriod(): bool;

    /**
     * Determine if the subscription has ended.
     */
    public function ended(): bool;

    /**
     * Determine if the subscription is recurring.
     */
    public function recurring(): bool;

    /**
     * Determine if the subscription is past due.
     */
    public function pastDue(): bool;

    /**
     * Determine if the subscription is incomplete.
     */
    public function incomplete(): bool;

    /**
     * Determine if the subscription has an incomplete payment.
     */
    public function hasIncompletePayment(): bool;

    /**
     * Determine if the subscription has a specific price.
     */
    public function hasPrice(string $price): bool;

    /**
     * Get the trial end date.
     */
    public function trialEndsAt(): ?CarbonInterface;

    /**
     * Get the subscription end date.
     */
    public function endsAt(): ?CarbonInterface;

    /**
     * Get the current period start.
     */
    public function currentPeriodStart(): ?CarbonInterface;

    /**
     * Get the current period end.
     */
    public function currentPeriodEnd(): ?CarbonInterface;

    /**
     * Get the subscription quantity.
     */
    public function quantity(): ?int;

    /**
     * Get the subscription items.
     *
     * @return Collection<int, SubscriptionItemContract>
     */
    public function items(): Collection;

    /**
     * Get the owner of the subscription.
     */
    public function owner(): BillableContract;

    /**
     * Cancel the subscription at period end.
     */
    public function cancel(): static;

    /**
     * Cancel the subscription immediately.
     */
    public function cancelNow(): static;

    /**
     * Cancel the subscription immediately and invoice.
     */
    public function cancelNowAndInvoice(): static;

    /**
     * Resume a canceled subscription.
     */
    public function resume(): static;

    /**
     * Swap to a new price.
     *
     * @param  string|array<string>  $prices
     * @param  array<string, mixed>  $options
     */
    public function swap(string | array $prices, array $options = []): static;

    /**
     * Update the quantity.
     */
    public function updateQuantity(int $quantity, ?string $price = null): static;

    /**
     * Increment the quantity.
     */
    public function incrementQuantity(int $count = 1, ?string $price = null): static;

    /**
     * Decrement the quantity.
     */
    public function decrementQuantity(int $count = 1, ?string $price = null): static;

    /**
     * Get the underlying gateway subscription object.
     */
    public function asGatewaySubscription(): mixed;
}
