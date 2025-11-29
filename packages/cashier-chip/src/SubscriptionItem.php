<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Database\Factories\SubscriptionItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * CHIP Subscription Item Model
 *
 * @property Subscription|null $subscription
 */
class SubscriptionItem extends Model
{
    /** @use HasFactory<SubscriptionItemFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SubscriptionItemFactory
    {
        return SubscriptionItemFactory::new();
    }

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
     */
    public function subscription(): BelongsTo
    {
        $model = CashierChip::$subscriptionModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Increment the quantity of the subscription item.
     *
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
     * @return $this
     */
    public function updateQuantity(int $quantity)
    {
        $this->subscription->guardAgainstIncomplete();

        return DB::transaction(function () use ($quantity) {
            $this->fill([
                'quantity' => $quantity,
            ])->save();

            if ($this->subscription->hasSinglePrice()) {
                $this->subscription->fill([
                    'quantity' => $quantity,
                ])->save();
            }

            return $this;
        });
    }

    /**
     * Swap the subscription item to a new price.
     *
     * @return $this
     */
    public function swap(string $price, array $options = [])
    {
        $this->subscription->guardAgainstIncomplete();

        return DB::transaction(function () use ($price, $options) {
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
        });
    }

    /**
     * Determine if the subscription item is currently within its trial period.
     */
    public function onTrial(): bool
    {
        return $this->subscription->onTrial();
    }

    /**
     * Determine if the subscription item is on a grace period after cancellation.
     */
    public function onGracePeriod(): bool
    {
        return $this->subscription->onGracePeriod();
    }

    /**
     * Get the total amount for this item (unit_amount * quantity).
     */
    public function totalAmount(): int
    {
        return ($this->unit_amount ?? 0) * ($this->quantity ?? 1);
    }
}
