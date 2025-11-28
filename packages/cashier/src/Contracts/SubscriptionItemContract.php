<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Contract for subscription item wrappers.
 *
 * This contract defines the interface for subscription item wrappers that adapt
 * underlying gateway subscription items to a unified interface.
 *
 * @extends Arrayable<string, mixed>
 */
interface SubscriptionItemContract extends Arrayable
{
    /**
     * Get the item's local ID.
     */
    public function id(): string;

    /**
     * Get the item's gateway ID.
     */
    public function gatewayId(): string;

    /**
     * Get the gateway name.
     */
    public function gateway(): string;

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
    public function quantity(): ?int;

    /**
     * Update the quantity.
     */
    public function updateQuantity(int $quantity): static;

    /**
     * Increment the quantity.
     */
    public function incrementQuantity(int $count = 1): static;

    /**
     * Decrement the quantity.
     */
    public function decrementQuantity(int $count = 1): static;

    /**
     * Swap to a new price.
     *
     * @param  array<string, mixed>  $options
     */
    public function swap(string $price, array $options = []): static;

    /**
     * Get the underlying gateway subscription item.
     */
    public function asGatewayItem(): mixed;
}
