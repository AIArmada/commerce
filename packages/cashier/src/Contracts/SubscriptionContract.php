<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contract for subscription models.
 */
interface SubscriptionContract
{
    /**
     * Get the subscription's gateway.
     */
    public function gateway(): string;

    /**
     * Get the subscription's gateway ID.
     */
    public function gatewayId(): string;

    /**
     * Get the subscription type.
     */
    public function type(): string;

    /**
     * Get the subscription status.
     */
    public function status(): string;

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
     * Determine if the subscription has a specific price.
     */
    public function hasPrice(string $price): bool;

    /**
     * Determine if the subscription has a specific product.
     */
    public function hasProduct(string $product): bool;

    /**
     * Get the trial end date.
     */
    public function trialEndsAt(): ?CarbonInterface;

    /**
     * Get the end date.
     */
    public function endsAt(): ?CarbonInterface;

    /**
     * Cancel the subscription at period end.
     */
    public function cancel(): self;

    /**
     * Cancel the subscription immediately.
     */
    public function cancelNow(): self;

    /**
     * Cancel the subscription immediately and invoice.
     */
    public function cancelNowAndInvoice(): self;

    /**
     * Resume a canceled subscription.
     */
    public function resume(): self;

    /**
     * Skip the trial.
     */
    public function skipTrial(): self;

    /**
     * Extend the trial.
     */
    public function extendTrial(CarbonInterface $date): self;

    /**
     * Swap to a new price.
     *
     * @param  string|array  $prices
     * @param  array<string, mixed>  $options
     */
    public function swap(string|array $prices, array $options = []): self;

    /**
     * Update the quantity.
     */
    public function updateQuantity(int $quantity, ?string $price = null): self;

    /**
     * Increment the quantity.
     */
    public function incrementQuantity(int $count = 1, ?string $price = null): self;

    /**
     * Decrement the quantity.
     */
    public function decrementQuantity(int $count = 1, ?string $price = null): self;

    /**
     * Get the quantity.
     */
    public function quantity(?string $price = null): int;

    /**
     * Get the subscription items.
     */
    public function items(): HasMany;

    /**
     * Get the owner of the subscription.
     */
    public function owner(): BelongsTo;

    /**
     * Sync the subscription with the gateway.
     */
    public function syncFromGateway(): self;

    /**
     * Get the underlying gateway subscription object.
     */
    public function asGatewaySubscription(): mixed;
}
