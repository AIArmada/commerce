<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Models;

use AIArmada\Cashier\Cashier;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Unified Subscription Model with multi-gateway support.
 *
 * This model manages subscriptions with a gateway column to track
 * which payment gateway is handling the subscription.
 */
class Subscription extends Model
{
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
    protected $table = 'gateway_subscriptions';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * The relations to eager load on every query.
     *
     * @var array<string>
     */
    protected $with = ['items'];

    /**
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'quantity' => 'integer',
            'trial_ends_at' => 'datetime',
            'next_billing_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Get the model related to the subscription.
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('billable');
    }

    /**
     * Get the subscription items related to the subscription.
     */
    public function items(): HasMany
    {
        return $this->hasMany(Cashier::$subscriptionItemModel);
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return $this->gateway;
    }

    /**
     * Get the gateway subscription ID.
     */
    public function gatewayId(): string
    {
        return $this->gateway_id;
    }

    /**
     * Determine if the subscription has multiple prices.
     */
    public function hasMultiplePrices(): bool
    {
        return is_null($this->gateway_price);
    }

    /**
     * Determine if the subscription has a single price.
     */
    public function hasSinglePrice(): bool
    {
        return ! $this->hasMultiplePrices();
    }

    /**
     * Determine if the subscription has a specific product.
     */
    public function hasProduct(string $product): bool
    {
        return $this->items->contains(function (SubscriptionItem $item) use ($product) {
            return $item->gateway_product === $product;
        });
    }

    /**
     * Determine if the subscription has a specific price.
     */
    public function hasPrice(string $price): bool
    {
        if ($this->hasMultiplePrices()) {
            return $this->items->contains(function (SubscriptionItem $item) use ($price) {
                return $item->gateway_price === $price;
            });
        }

        return $this->gateway_price === $price;
    }

    /**
     * Get the subscription item for the given price.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findItemOrFail(string $price): SubscriptionItem
    {
        return $this->items()->where('gateway_price', $price)->firstOrFail();
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is incomplete.
     */
    public function incomplete(): bool
    {
        return $this->gateway_status === self::STATUS_INCOMPLETE;
    }

    /**
     * Filter query by incomplete.
     */
    public function scopeIncomplete(Builder $query): void
    {
        $query->where('gateway_status', self::STATUS_INCOMPLETE);
    }

    /**
     * Determine if the subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->gateway_status === self::STATUS_PAST_DUE;
    }

    /**
     * Filter query by past due.
     */
    public function scopePastDue(Builder $query): void
    {
        $query->where('gateway_status', self::STATUS_PAST_DUE);
    }

    /**
     * Determine if the subscription is active.
     */
    public function active(): bool
    {
        return ! $this->ended() &&
            (! Cashier::$deactivateIncomplete || $this->gateway_status !== self::STATUS_INCOMPLETE) &&
            $this->gateway_status !== self::STATUS_INCOMPLETE_EXPIRED &&
            (! Cashier::$deactivatePastDue || $this->gateway_status !== self::STATUS_PAST_DUE) &&
            $this->gateway_status !== self::STATUS_UNPAID;
    }

    /**
     * Filter query by active.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where(function ($query) {
            $query->whereNull('ends_at')
                ->orWhere(function ($query) {
                    // On grace period - ends_at is set but in the future
                    $query->whereNotNull('ends_at')
                        ->where('ends_at', '>', Carbon::now());
                });
        })->where('gateway_status', '!=', self::STATUS_INCOMPLETE_EXPIRED)
            ->where('gateway_status', '!=', self::STATUS_UNPAID);

        if (Cashier::$deactivatePastDue) {
            $query->where('gateway_status', '!=', self::STATUS_PAST_DUE);
        }

        if (Cashier::$deactivateIncomplete) {
            $query->where('gateway_status', '!=', self::STATUS_INCOMPLETE);
        }
    }

    /**
     * Filter by gateway.
     */
    public function scopeForGateway(Builder $query, string $gateway): void
    {
        $query->where('gateway', $gateway);
    }

    /**
     * Determine if the subscription is recurring and not on trial.
     */
    public function recurring(): bool
    {
        return ! $this->onTrial() && ! $this->canceled();
    }

    /**
     * Determine if the subscription is no longer active.
     */
    public function canceled(): bool
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Filter query by canceled.
     */
    public function scopeCanceled(Builder $query): void
    {
        $query->whereNotNull('ends_at');
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     */
    public function ended(): bool
    {
        return $this->canceled() && ! $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is within its trial period.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Filter query by on trial.
     */
    public function scopeOnTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Determine if the subscription's trial has expired.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     */
    public function onGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Filter query by on grace period.
     */
    public function scopeOnGracePeriod(Builder $query): void
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Increment the quantity of the subscription.
     *
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

        $this->fill(['quantity' => $quantity])->save();

        $singleSubscriptionItem = $this->items()->firstOrFail();
        $singleSubscriptionItem->fill(['quantity' => $quantity])->save();

        return $this;
    }

    /**
     * Force the trial to end immediately.
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
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     */
    public function cancel()
    {
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
     * @internal
     */
    public function markAsCanceled(): void
    {
        $this->fill([
            'gateway_status' => self::STATUS_CANCELED,
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

        $this->fill([
            'gateway_status' => self::STATUS_ACTIVE,
            'ends_at' => null,
        ])->save();

        return $this;
    }

    /**
     * Get the current period start date for the subscription.
     */
    public function currentPeriodStart(DateTimeZone|string|int|null $timezone = null): ?CarbonInterface
    {
        if (! $this->next_billing_at) {
            return null;
        }

        $interval = $this->billing_interval ?? 'month';
        $start = $this->next_billing_at->copy()->sub($interval, 1);

        return $timezone ? $start->setTimezone($timezone) : $start;
    }

    /**
     * Get the current period end date for the subscription.
     */
    public function currentPeriodEnd(DateTimeZone|string|int|null $timezone = null): ?CarbonInterface
    {
        if (! $this->next_billing_at) {
            return null;
        }

        return $timezone ? $this->next_billing_at->copy()->setTimezone($timezone) : $this->next_billing_at->copy();
    }

    /**
     * Determine if the subscription has an incomplete payment.
     */
    public function hasIncompletePayment(): bool
    {
        return $this->pastDue() || $this->incomplete();
    }

    /**
     * Make sure a subscription is not incomplete when performing changes.
     *
     * @throws \AIArmada\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function guardAgainstIncomplete(): void
    {
        if ($this->incomplete()) {
            throw new \AIArmada\Cashier\Exceptions\SubscriptionUpdateFailure(
                'Cannot update an incomplete subscription.'
            );
        }
    }

    /**
     * Make sure a price argument is provided when the subscription has multiple prices.
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
