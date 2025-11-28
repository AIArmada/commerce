<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Models;

use AIArmada\Cashier\Cashier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unified Subscription Item Model with multi-gateway support.
 */
class SubscriptionItem extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'gateway_subscription_items';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'integer',
        ];
    }

    /**
     * Get the subscription that this item belongs to.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Cashier::$subscriptionModel);
    }

    /**
     * Get the gateway name from the parent subscription.
     */
    public function gateway(): string
    {
        return $this->subscription->gateway ?? 'stripe';
    }

    /**
     * Get the gateway item ID.
     */
    public function gatewayId(): string
    {
        return $this->gateway_id;
    }

    /**
     * Get the price ID.
     */
    public function priceId(): ?string
    {
        return $this->gateway_price;
    }

    /**
     * Get the product ID.
     */
    public function productId(): ?string
    {
        return $this->gateway_product;
    }

    /**
     * Increment the quantity of the subscription item.
     *
     * @return $this
     */
    public function incrementQuantity(int $count = 1)
    {
        return $this->updateQuantity(($this->quantity ?? 1) + $count);
    }

    /**
     * Decrement the quantity of the subscription item.
     *
     * @return $this
     */
    public function decrementQuantity(int $count = 1)
    {
        return $this->updateQuantity(max(1, ($this->quantity ?? 1) - $count));
    }

    /**
     * Update the quantity of the subscription item.
     *
     * @return $this
     */
    public function updateQuantity(int $quantity)
    {
        $this->fill(['quantity' => $quantity])->save();

        return $this;
    }

    /**
     * Swap the subscription item to a new price.
     *
     * @param  array<string, mixed>  $options
     * @return $this
     */
    public function swap(string $price, array $options = [])
    {
        $this->fill([
            'gateway_price' => $price,
            'gateway_product' => $options['product'] ?? $this->gateway_product,
        ])->save();

        return $this;
    }
}
