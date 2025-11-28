<?php

namespace AIArmada\CashierChip;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CHIP Subscription Item Model
 *
 * @property \AIArmada\CashierChip\Subscription|null $subscription
 */
class SubscriptionItem extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chip_subscription_items';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_amount' => 'integer',
    ];

    /**
     * Get the subscription that the item belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription(): BelongsTo
    {
        $model = CashierChip::$subscriptionModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Increment the quantity of the subscription item.
     *
     * @param  int  $count
     * @return $this
     */
    public function incrementQuantity(int $count = 1)
    {
        $this->updateQuantity($this->quantity + $count);

        return $this;
    }

    /**
     * Decrement the quantity of the subscription item.
     *
     * @param  int  $count
     * @return $this
     */
    public function decrementQuantity(int $count = 1)
    {
        $this->updateQuantity(max(1, $this->quantity - $count));

        return $this;
    }

    /**
     * Update the quantity of the subscription item.
     *
     * @param  int  $quantity
     * @return $this
     */
    public function updateQuantity(int $quantity)
    {
        $this->subscription->guardAgainstIncomplete();

        $this->fill([
            'quantity' => $quantity,
        ])->save();

        if ($this->subscription->hasSinglePrice()) {
            $this->subscription->fill([
                'quantity' => $quantity,
            ])->save();
        }

        return $this;
    }

    /**
     * Swap the subscription item to a new price.
     *
     * @param  string  $price
     * @param  array  $options
     * @return $this
     */
    public function swap(string $price, array $options = [])
    {
        $this->subscription->guardAgainstIncomplete();

        $this->fill([
            'chip_product' => $options['product'] ?? $this->chip_product,
            'chip_price' => $price,
            'unit_amount' => $options['unit_amount'] ?? $this->unit_amount,
        ])->save();

        if ($this->subscription->hasSinglePrice()) {
            $this->subscription->fill([
                'chip_price' => $price,
            ])->save();
        }

        return $this;
    }

    /**
     * Determine if the subscription item is currently within its trial period.
     *
     * @return bool
     */
    public function onTrial(): bool
    {
        return $this->subscription->onTrial();
    }

    /**
     * Determine if the subscription item is on a grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod(): bool
    {
        return $this->subscription->onGracePeriod();
    }

    /**
     * Get the total amount for this item (unit_amount * quantity).
     *
     * @return int
     */
    public function totalAmount(): int
    {
        return ($this->unit_amount ?? 0) * ($this->quantity ?? 1);
    }
}
