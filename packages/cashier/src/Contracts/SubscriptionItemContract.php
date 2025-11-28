<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contract for subscription item models.
 */
interface SubscriptionItemContract
{
    /**
     * Get the item's gateway ID.
     */
    public function gatewayId(): string;

    /**
     * Get the item's price ID.
     */
    public function priceId(): ?string;

    /**
     * Get the item's product ID.
     */
    public function productId(): ?string;

    /**
     * Get the item's quantity.
     */
    public function quantity(): int;

    /**
     * Get the item's unit amount in cents.
     */
    public function unitAmount(): ?int;

    /**
     * Update the quantity.
     */
    public function updateQuantity(int $quantity): self;

    /**
     * Increment the quantity.
     */
    public function incrementQuantity(int $count = 1): self;

    /**
     * Decrement the quantity.
     */
    public function decrementQuantity(int $count = 1): self;

    /**
     * Swap to a new price.
     *
     * @param  array<string, mixed>  $options
     */
    public function swap(string $price, array $options = []): self;

    /**
     * Get the parent subscription.
     */
    public function subscription(): BelongsTo;

    /**
     * Determine if the item is on trial.
     */
    public function onTrial(): bool;

    /**
     * Determine if the item is on grace period.
     */
    public function onGracePeriod(): bool;
}
