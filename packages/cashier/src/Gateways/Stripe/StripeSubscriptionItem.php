<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Stripe;

use AIArmada\Cashier\Contracts\SubscriptionItemContract;
use Carbon\CarbonInterface;
use Laravel\Cashier\SubscriptionItem;

/**
 * Wrapper for Stripe subscription item.
 */
class StripeSubscriptionItem implements SubscriptionItemContract
{
    /**
     * Create a new Stripe subscription item wrapper.
     */
    public function __construct(
        protected SubscriptionItem $item
    ) {}

    /**
     * Get the item ID.
     */
    public function id(): string
    {
        return (string) $this->item->getKey();
    }

    /**
     * Get the gateway item ID.
     */
    public function gatewayId(): string
    {
        return (string) $this->item->getAttribute('stripe_id');
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'stripe';
    }

    /**
     * Get the price ID.
     */
    public function priceId(): string
    {
        return (string) $this->item->getAttribute('stripe_price');
    }

    /**
     * Get the product ID.
     */
    public function productId(): ?string
    {
        $product = $this->item->getAttribute('stripe_product');

        return is_string($product) && $product !== '' ? $product : null;
    }

    /**
     * Get the quantity.
     */
    public function quantity(): ?int
    {
        $quantity = $this->item->getAttribute('quantity');

        return is_int($quantity) ? $quantity : null;
    }

    /**
     * Get the current period start.
     */
    public function currentPeriodStart(): ?CarbonInterface
    {
        return $this->item->currentPeriodStart();
    }

    /**
     * Get the current period end.
     */
    public function currentPeriodEnd(): ?CarbonInterface
    {
        return $this->item->currentPeriodEnd();
    }

    /**
     * Update the quantity.
     */
    public function updateQuantity(int $quantity): static
    {
        $this->item->updateQuantity($quantity);

        return $this;
    }

    /**
     * Increment the quantity.
     */
    public function incrementQuantity(int $count = 1): static
    {
        $this->item->incrementQuantity($count);

        return $this;
    }

    /**
     * Decrement the quantity.
     */
    public function decrementQuantity(int $count = 1): static
    {
        $this->item->decrementQuantity($count);

        return $this;
    }

    /**
     * Swap to a new price.
     *
     * @param  array<string, mixed>  $options
     */
    public function swap(string $price, array $options = []): static
    {
        $this->item->swap($price, $options);

        return $this;
    }

    /**
     * Get the underlying subscription item model.
     */
    public function asGatewayItem(): SubscriptionItem
    {
        return $this->item;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'gateway_id' => $this->gatewayId(),
            'gateway' => $this->gateway(),
            'price_id' => $this->priceId(),
            'product_id' => $this->productId(),
            'quantity' => $this->quantity(),
        ];
    }
}
