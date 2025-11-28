<?php

namespace AIArmada\CashierChip;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;

/**
 * CHIP Subscription Model
 *
 * Unlike Stripe, CHIP doesn't have native subscription management.
 * This model manages subscriptions locally with CHIP recurring tokens for payment.
 *
 * @property \AIArmada\CashierChip\Billable&\Illuminate\Database\Eloquent\Model $owner
 */
class Subscription extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_INCOMPLETE_EXPIRED = 'incomplete_expired';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAUSED = 'paused';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chip_subscriptions';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['items'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'ends_at' => 'datetime',
        'quantity' => 'integer',
        'trial_ends_at' => 'datetime',
        'next_billing_at' => 'datetime',
    ];

    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Get the model related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner(): BelongsTo
    {
        $model = CashierChip::$customerModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Get the subscription items related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(CashierChip::$subscriptionItemModel);
    }

    /**
     * Determine if the subscription has multiple prices.
     *
     * @return bool
     */
    public function hasMultiplePrices(): bool
    {
        return is_null($this->chip_price);
    }

    /**
     * Determine if the subscription has a single price.
     *
     * @return bool
     */
    public function hasSinglePrice(): bool
    {
        return ! $this->hasMultiplePrices();
    }

    /**
     * Determine if the subscription has a specific product.
     *
     * @param  string  $product
     * @return bool
     */
    public function hasProduct(string $product): bool
    {
        return $this->items->contains(function (SubscriptionItem $item) use ($product) {
            return $item->chip_product === $product;
        });
    }

    /**
     * Determine if the subscription has a specific price.
     *
     * @param  string  $price
     * @return bool
     */
    public function hasPrice(string $price): bool
    {
        if ($this->hasMultiplePrices()) {
            return $this->items->contains(function (SubscriptionItem $item) use ($price) {
                return $item->chip_price === $price;
            });
        }

        return $this->chip_price === $price;
    }

    /**
     * Get the subscription item for the given price.
     *
     * @param  string  $price
     * @return \AIArmada\CashierChip\SubscriptionItem
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findItemOrFail(string $price): SubscriptionItem
    {
        return $this->items()->where('chip_price', $price)->firstOrFail();
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is incomplete.
     *
     * @return bool
     */
    public function incomplete(): bool
    {
        return $this->chip_status === self::STATUS_INCOMPLETE;
    }

    /**
     * Filter query by incomplete.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeIncomplete(Builder $query): void
    {
        $query->where('chip_status', self::STATUS_INCOMPLETE);
    }

    /**
     * Determine if the subscription is past due.
     *
     * @return bool
     */
    public function pastDue(): bool
    {
        return $this->chip_status === self::STATUS_PAST_DUE;
    }

    /**
     * Filter query by past due.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopePastDue(Builder $query): void
    {
        $query->where('chip_status', self::STATUS_PAST_DUE);
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active(): bool
    {
        return ! $this->ended() &&
            (! CashierChip::$deactivateIncomplete || $this->chip_status !== self::STATUS_INCOMPLETE) &&
            $this->chip_status !== self::STATUS_INCOMPLETE_EXPIRED &&
            (! CashierChip::$deactivatePastDue || $this->chip_status !== self::STATUS_PAST_DUE) &&
            $this->chip_status !== self::STATUS_UNPAID;
    }

    /**
     * Filter query by active.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeActive(Builder $query): void
    {
        $query->where(function ($query) {
            $query->whereNull('ends_at')
                ->orWhere(function ($query) {
                    $query->onGracePeriod();
                });
        })->where('chip_status', '!=', self::STATUS_INCOMPLETE_EXPIRED)
            ->where('chip_status', '!=', self::STATUS_UNPAID);

        if (CashierChip::$deactivatePastDue) {
            $query->where('chip_status', '!=', self::STATUS_PAST_DUE);
        }

        if (CashierChip::$deactivateIncomplete) {
            $query->where('chip_status', '!=', self::STATUS_INCOMPLETE);
        }
    }

    /**
     * Determine if the subscription is recurring and not on trial.
     *
     * @return bool
     */
    public function recurring(): bool
    {
        return ! $this->onTrial() && ! $this->canceled();
    }

    /**
     * Filter query by recurring.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeRecurring(Builder $query): void
    {
        $query->notOnTrial()->notCanceled();
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function canceled(): bool
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Filter query by canceled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeCanceled(Builder $query): void
    {
        $query->whereNotNull('ends_at');
    }

    /**
     * Filter query by not canceled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotCanceled(Builder $query): void
    {
        $query->whereNull('ends_at');
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     *
     * @return bool
     */
    public function ended(): bool
    {
        return $this->canceled() && ! $this->onGracePeriod();
    }

    /**
     * Filter query by ended.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeEnded(Builder $query): void
    {
        $query->canceled()->notOnGracePeriod();
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Filter query by on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Determine if the subscription's trial has expired.
     *
     * @return bool
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Filter query by expired trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeExpiredTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', Carbon::now());
    }

    /**
     * Filter query by not on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnTrial(Builder $query): void
    {
        $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', Carbon::now());
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Filter query by on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnGracePeriod(Builder $query): void
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnGracePeriod(Builder $query): void
    {
        $query->whereNull('ends_at')->orWhere('ends_at', '<=', Carbon::now());
    }

    /**
     * Increment the quantity of the subscription.
     *
     * @param  int  $count
     * @param  string|null  $price
     * @return $this
     */
    public function incrementQuantity(int $count = 1, ?string $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)->incrementQuantity($count);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePrices();

        return $this->updateQuantity($this->quantity + $count, $price);
    }

    /**
     * Decrement the quantity of the subscription.
     *
     * @param  int  $count
     * @param  string|null  $price
     * @return $this
     */
    public function decrementQuantity(int $count = 1, ?string $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)->decrementQuantity($count);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePrices();

        return $this->updateQuantity(max(1, $this->quantity - $count), $price);
    }

    /**
     * Update the quantity of the subscription.
     *
     * @param  int  $quantity
     * @param  string|null  $price
     * @return $this
     */
    public function updateQuantity(int $quantity, ?string $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)->updateQuantity($quantity);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePrices();

        $this->fill([
            'quantity' => $quantity,
        ])->save();

        $singleSubscriptionItem = $this->items()->firstOrFail();

        $singleSubscriptionItem->fill([
            'quantity' => $quantity,
        ])->save();

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->trial_ends_at = null;

        return $this;
    }

    /**
     * Force the subscription's trial to end immediately.
     *
     * @return $this
     */
    public function endTrial()
    {
        if (is_null($this->trial_ends_at)) {
            return $this;
        }

        $this->trial_ends_at = null;
        $this->save();

        return $this;
    }

    /**
     * Extend an existing subscription's trial period.
     *
     * @param  \Carbon\CarbonInterface  $date
     * @return $this
     */
    public function extendTrial(CarbonInterface $date)
    {
        if (! $date->isFuture()) {
            throw new InvalidArgumentException("Extending a subscription's trial requires a date in the future.");
        }

        $this->trial_ends_at = $date;
        $this->save();

        return $this;
    }

    /**
     * Swap the subscription to new prices.
     *
     * @param  string|array  $prices
     * @param  array  $options
     * @return $this
     */
    public function swap(string|array $prices, array $options = [])
    {
        if (empty($prices = (array) $prices)) {
            throw new InvalidArgumentException('Please provide at least one price when swapping.');
        }

        $this->guardAgainstIncomplete();

        $isSinglePrice = count($prices) === 1;
        $firstPrice = is_string(array_values($prices)[0])
            ? array_values($prices)[0]
            : array_keys($prices)[0];

        // Delete existing items and create new ones
        $this->items()->delete();

        foreach ($prices as $priceKey => $priceValue) {
            $price = is_string($priceValue) ? $priceValue : $priceKey;
            $quantity = is_array($priceValue) ? ($priceValue['quantity'] ?? 1) : 1;

            $this->items()->create([
                'chip_id' => 'si_'.uniqid().'_'.time(),
                'chip_product' => $options['product'] ?? null,
                'chip_price' => $price,
                'quantity' => $quantity,
            ]);
        }

        $this->fill([
            'chip_price' => $isSinglePrice ? $firstPrice : null,
            'quantity' => $isSinglePrice ? ($this->items()->first()->quantity ?? null) : null,
            'ends_at' => null,
        ])->save();

        $this->unsetRelation('items');

        return $this;
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     */
    public function cancel()
    {
        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll use the next billing date as the end of
        // the grace period for this current user.
        if ($this->onTrial()) {
            $this->ends_at = $this->trial_ends_at;
        } else {
            $this->ends_at = $this->next_billing_at ?? Carbon::now();
        }

        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription at a specific moment in time.
     *
     * @param  \DateTimeInterface|int  $endsAt
     * @return $this
     */
    public function cancelAt(DateTimeInterface|int $endsAt)
    {
        if ($endsAt instanceof DateTimeInterface) {
            $endsAt = Carbon::instance($endsAt);
        } else {
            $endsAt = Carbon::createFromTimestamp($endsAt);
        }

        $this->ends_at = $endsAt;
        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $this->markAsCanceled();

        return $this;
    }

    /**
     * Mark the subscription as canceled.
     *
     * @return void
     *
     * @internal
     */
    public function markAsCanceled(): void
    {
        $this->fill([
            'chip_status' => self::STATUS_CANCELED,
            'ends_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Resume the canceled subscription.
     *
     * @return $this
     *
     * @throws \LogicException
     */
    public function resume()
    {
        if (! $this->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "canceled". Then we shall save this record in the database.
        $this->fill([
            'chip_status' => self::STATUS_ACTIVE,
            'ends_at' => null,
        ])->save();

        return $this;
    }

    /**
     * Get the current period start date for the subscription.
     *
     * @param  \DateTimeZone|string|int|null  $timezone
     * @return \Carbon\CarbonInterface|null
     */
    public function currentPeriodStart(DateTimeZone|string|int|null $timezone = null): ?CarbonInterface
    {
        if (! $this->next_billing_at) {
            return null;
        }

        // Calculate the period start based on billing interval
        $interval = $this->billing_interval ?? 'month';
        $start = $this->next_billing_at->copy()->sub($interval, 1);

        return $timezone ? $start->setTimezone($timezone) : $start;
    }

    /**
     * Get the current period end date for the subscription.
     *
     * @param  \DateTimeZone|string|int|null  $timezone
     * @return \Carbon\CarbonInterface|null
     */
    public function currentPeriodEnd(DateTimeZone|string|int|null $timezone = null): ?CarbonInterface
    {
        if (! $this->next_billing_at) {
            return null;
        }

        return $timezone ? $this->next_billing_at->copy()->setTimezone($timezone) : $this->next_billing_at->copy();
    }

    /**
     * Charge the subscription using the default payment method (recurring token).
     *
     * @param  int|null  $amount
     * @return \AIArmada\CashierChip\Payment
     */
    public function charge(?int $amount = null)
    {
        $amount = $amount ?? $this->calculateSubscriptionAmount();

        return $this->owner->chargeWithRecurringToken(
            $amount,
            $this->owner->defaultPaymentMethod(),
            [
                'reference' => "Subscription {$this->type} - Period {$this->next_billing_at?->format('Y-m-d')}",
            ]
        );
    }

    /**
     * Calculate the total subscription amount based on items.
     *
     * @return int
     */
    protected function calculateSubscriptionAmount(): int
    {
        // This should be implemented based on your pricing logic
        // For now, return a default value that should be overridden
        return $this->items->sum(function ($item) {
            return ($item->unit_amount ?? 0) * ($item->quantity ?? 1);
        });
    }

    /**
     * Get the recurring token (payment method) for this subscription.
     *
     * @return string|null
     */
    public function recurringToken(): ?string
    {
        return $this->recurring_token ?? $this->owner->defaultPaymentMethod();
    }

    /**
     * Set the recurring token for this subscription.
     *
     * @param  string  $token
     * @return $this
     */
    public function setRecurringToken(string $token)
    {
        $this->recurring_token = $token;
        $this->save();

        return $this;
    }

    /**
     * Determine if the subscription has an incomplete payment.
     *
     * @return bool
     */
    public function hasIncompletePayment(): bool
    {
        return $this->pastDue() || $this->incomplete();
    }

    /**
     * Make sure a subscription is not incomplete when performing changes.
     *
     * @return void
     *
     * @throws \AIArmada\CashierChip\Exceptions\SubscriptionUpdateFailure
     */
    public function guardAgainstIncomplete(): void
    {
        if ($this->incomplete()) {
            throw new \AIArmada\CashierChip\Exceptions\SubscriptionUpdateFailure(
                'Cannot update an incomplete subscription.'
            );
        }
    }

    /**
     * Make sure a price argument is provided when the subscription is a subscription with multiple prices.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function guardAgainstMultiplePrices(): void
    {
        if ($this->hasMultiplePrices()) {
            throw new InvalidArgumentException(
                'This method requires a price argument since the subscription has multiple prices.'
            );
        }
    }
}
