<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Subscription;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Billing\Coupon;
use AIArmada\CashierChip\Billing\Discount;
use AIArmada\CashierChip\Billing\VoucherIntegration;
use AIArmada\CashierChip\Concerns\HandlesPaymentFailures;
use AIArmada\CashierChip\Concerns\InteractsWithPaymentBehavior;
use AIArmada\CashierChip\Concerns\Prorates;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Database\Factories\SubscriptionFactory;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Exceptions\InvalidCoupon;
use AIArmada\CashierChip\Exceptions\SubscriptionUpdateFailure;
use AIArmada\CashierChip\Invoice\Invoice;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Akaunting\Money\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * CHIP Subscription Model
 *
 * Unlike Stripe, CHIP doesn't have native subscription management.
 * This model manages subscriptions locally with CHIP recurring tokens for payment.
 *
 * @property string $id
 * @property string $billable_type
 * @property string $billable_id
 * @property string $type
 * @property string $chip_id
 * @property SubscriptionStatus $chip_status
 * @property string|null $chip_price
 * @property int|null $quantity
 * @property string|null $recurring_token
 * @property string $billing_interval
 * @property int $billing_interval_count
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $next_billing_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $canceled_at
 * @property CarbonImmutable|null $paused_at
 * @property CarbonImmutable|null $past_due_at
 * @property CarbonImmutable|null $trial_started_at
 * @property CarbonImmutable|null $renewed_at
 * @property string|null $coupon_id
 * @property int|null $coupon_discount
 * @property string|null $coupon_duration
 * @property CarbonImmutable|null $coupon_applied_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property-read Model&BillableContract $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SubscriptionItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RenewalAttempt> $renewalAttempts
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> whereCanceled()
 * @method static \Illuminate\Database\Eloquent\Builder<static> whereNotOnTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static> whereOnGracePeriod()
 */
class Subscription extends Model
{
    use HandlesPaymentFailures;

    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    use HasOwner {
        scopeForOwner as private scopeForOwnerUsingTrait;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use InteractsWithPaymentBehavior;
    use Prorates;

    protected static string $ownerScopeConfigKey = 'cashier-chip.features.owner';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'billable_type',
        'billable_id',
        'type',
        'chip_id',
        'chip_status',
        'chip_price',
        'quantity',
        'recurring_token',
        'billing_interval',
        'billing_interval_count',
        'trial_ends_at',
        'next_billing_at',
        'ends_at',
        'canceled_at',
        'paused_at',
        'past_due_at',
        'trial_started_at',
        'renewed_at',
        'coupon_id',
        'coupon_discount',
        'coupon_duration',
        'coupon_applied_at',
    ];

    public function getTable(): string
    {
        $tables = config('cashier-chip.database.tables', []);
        $prefix = config('cashier-chip.database.table_prefix', 'cashier_chip_');

        return $tables['subscriptions'] ?? $prefix . 'subscriptions';
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): MorphTo
    {
        return $this->billable();
    }

    /**
     * Get the billable customer model related to the subscription.
     *
     * @return MorphTo<Model, $this>
     */
    public function customer(): MorphTo
    {
        return $this->billable();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the subscription items related to the subscription.
     *
     * @return HasMany<SubscriptionItem, $this>
     */
    public function items(): HasMany
    {
        /** @var class-string<SubscriptionItem> $model */
        $model = Cashier::$subscriptionItemModel;

        return $this->hasMany($model);
    }

    /**
     * Get the renewal attempts recorded for the subscription.
     *
     * @return HasMany<RenewalAttempt, $this>
     */
    public function renewalAttempts(): HasMany
    {
        return $this->hasMany(RenewalAttempt::class, 'subscription_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    final public function scopeForOwner(Builder $query, ?Model $owner = null, ?bool $includeGlobal = null): Builder
    {
        if (! (bool) config('cashier-chip.features.owner.enabled', false)) {
            return $query;
        }

        $owner ??= $this->resolveOwner();
        $includeGlobal ??= (bool) config('cashier-chip.features.owner.include_global', false);

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            sprintf('%s requires an owner context or explicit global context.', self::class),
        );

        return $this->scopeForOwnerUsingTrait($query, $owner, $includeGlobal);
    }

    /**
     * Determine if the subscription has multiple prices.
     */
    public function hasMultiplePrices(): bool
    {
        return is_null($this->chip_price);
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
        $this->loadMissing('items');

        return $this->items->contains(function (SubscriptionItem $item) use ($product) {
            return $item->chip_product === $product;
        });
    }

    /**
     * Determine if the subscription has a specific price.
     */
    public function hasPrice(string $price): bool
    {
        if ($this->hasMultiplePrices()) {
            $this->loadMissing('items');

            return $this->items->contains(function (SubscriptionItem $item) use ($price) {
                return $item->chip_price === $price;
            });
        }

        return $this->chip_price === $price;
    }

    /**
     * Get the subscription item for the given price.
     *
     *
     * @throws ModelNotFoundException
     */
    public function findItemOrFail(string $price): SubscriptionItem
    {
        return $this->items()->where('chip_price', $price)->firstOrFail();
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
        return $this->chip_status === SubscriptionStatus::Incomplete;
    }

    /**
     * Filter query by incomplete.
     */
    public function scopeWhereIncomplete(Builder $query): void
    {
        $query->where('chip_status', SubscriptionStatus::Incomplete);
    }

    /**
     * Filter query by incomplete.
     */
    public function scopeIncomplete(Builder $query): void
    {
        $this->scopeWhereIncomplete($query);
    }

    /**
     * Determine if the subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->chip_status === SubscriptionStatus::PastDue;
    }

    /**
     * Filter query by past due.
     */
    public function scopeWherePastDue(Builder $query): void
    {
        $query->where('chip_status', SubscriptionStatus::PastDue);
    }

    /**
     * Filter query by past due.
     */
    public function scopePastDue(Builder $query): void
    {
        $this->scopeWherePastDue($query);
    }

    /**
     * Determine if the subscription is active.
     */
    public function active(): bool
    {
        return ! $this->ended() &&
            (! Cashier::$deactivateIncomplete || $this->chip_status !== SubscriptionStatus::Incomplete) &&
            $this->chip_status !== SubscriptionStatus::IncompleteExpired &&
            (! Cashier::$deactivatePastDue || $this->chip_status !== SubscriptionStatus::PastDue) &&
            $this->chip_status !== SubscriptionStatus::Unpaid;
    }

    /**
     * Filter query by active.
     */
    public function scopeWhereActive(Builder $query): void
    {
        $query->where(function ($query): void {
            $query->whereNull('ends_at')
                ->orWhere(function ($query): void {
                    $query->whereNotNull('ends_at')
                        ->where('ends_at', '>', CarbonImmutable::now());
                });
        })->where('chip_status', '!=', SubscriptionStatus::IncompleteExpired)
            ->where('chip_status', '!=', SubscriptionStatus::Unpaid);

        if (Cashier::$deactivatePastDue) {
            $query->where('chip_status', '!=', SubscriptionStatus::PastDue);
        }

        if (Cashier::$deactivateIncomplete) {
            $query->where('chip_status', '!=', SubscriptionStatus::Incomplete);
        }
    }

    /**
     * Determine if the subscription is recurring and not on trial.
     */
    public function recurring(): bool
    {
        return ! $this->onTrial() && ! $this->canceled();
    }

    /**
     * Filter query by recurring.
     */
    public function scopeWhereRecurring(Builder $query): void
    {
        $query
            ->where(function (Builder $query): void {
                $query->whereNull('trial_ends_at')
                    ->orWhere('trial_ends_at', '<=', CarbonImmutable::now());
            })
            ->whereNull('ends_at');
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
    public function scopeWhereCanceled(Builder $query): void
    {
        $query->whereNotNull('ends_at');
    }

    /**
     * Filter query by not canceled.
     */
    public function scopeNotCanceled(Builder $query): void
    {
        $query->whereNull('ends_at');
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     */
    public function ended(): bool
    {
        return $this->canceled() && ! $this->onGracePeriod();
    }

    /**
     * Filter query by ended.
     */
    public function scopeWhereEnded(Builder $query): void
    {
        $query->whereNotNull('ends_at')
            ->where('ends_at', '<=', CarbonImmutable::now());
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
    public function scopeWhereOnTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', CarbonImmutable::now());
    }

    /**
     * Determine if the subscription's trial has expired.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Filter query by expired trial.
     */
    public function scopeExpiredTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', CarbonImmutable::now());
    }

    /**
     * Filter query by not on trial.
     */
    public function scopeNotOnTrial(Builder $query): void
    {
        $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', CarbonImmutable::now());
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
    public function scopeWhereOnGracePeriod(Builder $query): void
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', CarbonImmutable::now());
    }

    /**
     * Filter query by not on grace period.
     */
    public function scopeNotOnGracePeriod(Builder $query): void
    {
        $query->whereNull('ends_at')->orWhere('ends_at', '<=', CarbonImmutable::now());
    }

    /**
     * Shorthand scope for active subscriptions.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $this->scopeWhereActive($query);
    }

    /**
     * Shorthand scope for paused subscriptions.
     *
     * @param  Builder<static>  $query
     */
    public function scopePaused(Builder $query): void
    {
        $this->scopeWherePaused($query);
    }

    /**
     * Shorthand scope for canceled subscriptions.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCanceled(Builder $query): void
    {
        $this->scopeWhereCanceled($query);
    }

    /**
     * Shorthand scope for recurring subscriptions.
     *
     * @param  Builder<static>  $query
     */
    public function scopeRecurring(Builder $query): void
    {
        $this->scopeWhereRecurring($query);
    }

    /**
     * Shorthand scope for subscriptions on trial.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOnTrial(Builder $query): void
    {
        $this->scopeWhereOnTrial($query);
    }

    /**
     * Shorthand scope for subscriptions on grace period.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOnGracePeriod(Builder $query): void
    {
        $this->scopeWhereOnGracePeriod($query);
    }

    /**
     * Shorthand scope for ended subscriptions.
     *
     * @param  Builder<static>  $query
     */
    public function scopeEnded(Builder $query): void
    {
        $this->scopeWhereEnded($query);
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

        return $this->updateQuantity(((int) $this->quantity) + $count, $price);
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

        $quantity = max(1, $quantity);

        if ($price) {
            $this->findItemOrFail($price)->updateQuantity($quantity);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePrices();

        return DB::transaction(function () use ($quantity) {
            $this->fill([
                'quantity' => $quantity,
            ])->save();

            $singleSubscriptionItem = $this->items()->firstOrFail();

            $singleSubscriptionItem->fill([
                'quantity' => $quantity,
            ])->save();

            return $this;
        });
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
     * @return $this
     */
    public function extendTrial(CarbonInterface $date)
    {
        if (! $date->isFuture()) {
            throw new InvalidArgumentException("Extending a subscription's trial requires a date in the future.");
        }

        $this->trial_ends_at = CarbonImmutable::instance($date);
        $this->save();

        return $this;
    }

    /**
     * Swap the subscription to new prices.
     *
     * @return $this
     */
    public function swap(string | array $prices, array $options = [])
    {
        if (empty($prices = (array) $prices)) {
            throw new InvalidArgumentException('Please provide at least one price when swapping.');
        }

        $this->guardAgainstIncomplete();

        return DB::transaction(function () use ($prices, $options) {
            $isSinglePrice = count($prices) === 1;
            $firstPrice = is_string(array_values($prices)[0])
                ? array_values($prices)[0]
                : array_keys($prices)[0];

            $existingItemIds = $this->items()->pluck('id')->all();
            $createdItems = [];

            foreach ($prices as $priceKey => $priceValue) {
                $price = is_string($priceValue) ? $priceValue : $priceKey;
                $quantity = is_array($priceValue) ? ($priceValue['quantity'] ?? 1) : 1;
                $quantity = max(1, (int) $quantity);

                $createdItems[] = $this->createTrustedSubscriptionItem([
                    'owner_type' => $this->owner_type,
                    'owner_id' => $this->owner_id,
                    'chip_id' => Str::orderedUuid()->toString(),
                    'chip_product' => $options['product'] ?? null,
                    'chip_price' => $price,
                    'quantity' => $quantity,
                ]);
            }

            if ($existingItemIds !== []) {
                $this->items()->whereIn('id', $existingItemIds)->delete();
            }

            $firstNewItem = $createdItems[0];

            $this->fill([
                'chip_price' => $isSinglePrice ? $firstPrice : null,
                'quantity' => $isSinglePrice ? $firstNewItem?->quantity : null,
                'ends_at' => null,
            ])->save();

            $this->unsetRelation('items');

            return $this;
        });
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
            $this->ends_at = $this->next_billing_at ?? CarbonImmutable::now();
        }

        $this->canceled_at = CarbonImmutable::now();
        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription at a specific moment in time.
     *
     * @return $this
     */
    public function cancelAt(DateTimeInterface | int $endsAt)
    {
        if ($endsAt instanceof DateTimeInterface) {
            $endsAt = CarbonImmutable::instance($endsAt);
        } else {
            $endsAt = CarbonImmutable::createFromTimestamp($endsAt);
        }

        $this->ends_at = $endsAt;
        $this->canceled_at = CarbonImmutable::now();
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
     *
     * @internal
     */
    public function markAsCanceled(): void
    {
        $this->forceFill([
            'chip_status' => SubscriptionStatus::Canceled,
            'ends_at' => CarbonImmutable::now(),
            'canceled_at' => CarbonImmutable::now(),
        ])->save();
    }

    /**
     * Resume the canceled subscription.
     *
     * @return $this
     *
     * @throws LogicException
     */
    public function resume()
    {
        if (! $this->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "canceled". Then we shall save this record in the database.
        $this->forceFill([
            'chip_status' => SubscriptionStatus::Active,
            'ends_at' => null,
            'canceled_at' => null,
        ])->save();

        return $this;
    }

    /**
     * Get the current period start date for the subscription.
     */
    public function currentPeriodStart(DateTimeZone | string | int | null $timezone = null): ?CarbonInterface
    {
        if (! $this->next_billing_at) {
            return null;
        }

        // Calculate the period start based on billing interval
        $interval = $this->billing_interval ?? 'month';
        $intervalCount = $this->billing_interval_count > 0 ? $this->billing_interval_count : 1;
        $start = $this->next_billing_at->copy()->sub($interval, $intervalCount);

        return $timezone ? $start->setTimezone($timezone) : $start;
    }

    /**
     * Get the current period end date for the subscription.
     */
    public function currentPeriodEnd(DateTimeZone | string | int | null $timezone = null): ?CarbonInterface
    {
        if (! $this->next_billing_at) {
            return null;
        }

        return $timezone ? $this->next_billing_at->copy()->setTimezone($timezone) : $this->next_billing_at->copy();
    }

    /**
     * Charge the subscription using the default payment method (recurring token).
     *
     * @return Payment
     */
    public function charge(?int $amount = null)
    {
        $amount = $amount ?? $this->calculateSubscriptionAmount();
        Cashier::assertAmountWithinBounds($amount);

        $recurringTokenId = $this->customer->defaultPaymentMethod()?->id();

        return $this->customer->chargeWithRecurringToken(
            $amount,
            $recurringTokenId,
            [
                'reference' => "Subscription {$this->type} - Period {$this->next_billing_at?->format('Y-m-d')}",
            ]
        );
    }

    /**
     * Get the recurring token (payment method) for this subscription.
     */
    public function recurringToken(): ?string
    {
        return $this->recurring_token ?? $this->customer?->defaultPaymentMethod()?->id();
    }

    /**
     * Set the recurring token for this subscription.
     *
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
     */
    public function hasIncompletePayment(): bool
    {
        return $this->pastDue() || $this->incomplete();
    }

    /**
     * Determine if the subscription has a discount applied.
     */
    public function hasDiscount(): bool
    {
        return ! is_null($this->coupon_id);
    }

    /**
     * The discount that applies to the subscription, if applicable.
     */
    public function discount(): ?Discount
    {
        if (! $this->hasDiscount()) {
            return null;
        }

        $billable = $this->billableModel();

        if (! $billable instanceof BillableContract) {
            return null;
        }

        return new Discount([
            'coupon' => $this->coupon_id,
            'amount' => $this->coupon_discount,
            'start' => $this->coupon_applied_at,
            'end' => $this->calculateDiscountEnd(),
            'currency' => $billable->preferredCurrency(),
        ]);
    }

    /**
     * Get all discounts that apply to the subscription.
     *
     * @return Collection<int, Discount>
     */
    public function discounts(): Collection
    {
        $discount = $this->discount();

        if (! $discount) {
            return collect();
        }

        return collect([$discount]);
    }

    /**
     * Apply a coupon to the subscription.
     *
     * @throws InvalidCoupon
     */
    public function applyCoupon(string $couponId): void
    {
        $this->validateCouponForApplication($couponId);

        $coupon = $this->retrieveCoupon($couponId);

        if (! $coupon) {
            throw InvalidCoupon::notFound($couponId);
        }

        $totalAmount = $this->calculateSubscriptionAmount();
        $discount = $coupon->calculateDiscount($totalAmount);

        $this->fill([
            'coupon_id' => $couponId,
            'coupon_discount' => $discount,
            'coupon_duration' => $coupon->duration(),
            'coupon_applied_at' => CarbonImmutable::now(),
        ])->save();

        // Record coupon usage
        $this->recordCouponUsage($couponId, $discount);
    }

    /**
     * Apply a promotion code to the subscription.
     */
    public function applyPromotionCode(string $promotionCodeId): void
    {
        // For CHIP/Vouchers, promotion codes are the same as coupon codes
        $this->applyCoupon($promotionCodeId);
    }

    /**
     * Remove the discount from the subscription.
     */
    public function removeDiscount(): void
    {
        $this->fill([
            'coupon_id' => null,
            'coupon_discount' => null,
            'coupon_duration' => null,
            'coupon_applied_at' => null,
        ])->save();
    }

    /**
     * Get the latest payment for a Subscription.
     */
    public function latestPayment(): ?Payment
    {
        $billable = $this->billableModel();
        $attempt = $this->renewalAttempts()
            ->whereNotNull('purchase_id')
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $attempt instanceof RenewalAttempt) {
            return null;
        }

        $purchase = $this->purchaseForAttempt($attempt);

        if (! $purchase instanceof PurchaseData) {
            return null;
        }

        $payment = new Payment($purchase);

        if ($billable instanceof Model && $billable instanceof BillableContract) {
            $payment->setCustomer($billable);
        }

        return $payment;
    }

    /**
     * Sync the CHIP status of the subscription.
     *
     * Since CHIP doesn't have native subscriptions, this method
     * recalculates the status based on local state.
     */
    public function syncChipStatus(): void
    {
        $status = $this->calculateCurrentStatus();

        $this->chip_status = $status;
        $this->save();
    }

    /**
     * Add a new price to the subscription.
     *
     * @return $this
     *
     * @throws SubscriptionUpdateFailure
     */
    public function addPrice(string $price, ?int $quantity = 1, array $options = []): static
    {
        $this->guardAgainstIncomplete();

        return DB::transaction(function () use ($price, $quantity, $options): static {
            $this->loadMissing('items');

            if ($this->items->contains('chip_price', $price)) {
                throw SubscriptionUpdateFailure::duplicatePrice($this, $price);
            }

            $this->createTrustedSubscriptionItem([
                'owner_type' => $this->owner_type,
                'owner_id' => $this->owner_id,
                'chip_id' => Str::orderedUuid()->toString(),
                'chip_product' => $options['product'] ?? null,
                'chip_price' => $price,
                'quantity' => $quantity,
                'unit_amount' => $options['unit_amount'] ?? null,
            ]);

            $this->unsetRelation('items');

            if ($this->hasSinglePrice()) {
                $this->fill([
                    'chip_price' => null,
                    'quantity' => null,
                ])->save();
            }

            return $this;
        });
    }

    /**
     * Remove a price from the subscription.
     *
     * @return $this
     *
     * @throws SubscriptionUpdateFailure
     */
    public function removePrice(string $price): static
    {
        if ($this->hasSinglePrice()) {
            throw SubscriptionUpdateFailure::cannotDeleteLastPrice($this);
        }

        $this->items()->where('chip_price', $price)->delete();

        $this->unsetRelation('items');

        if ($this->items()->count() < 2) {
            $item = $this->items()->first();

            if (! $item instanceof SubscriptionItem) {
                return $this;
            }

            $this->fill([
                'chip_price' => $item->chip_price,
                'quantity' => $item->quantity,
            ])->save();
        }

        return $this;
    }

    /**
     * Get the upcoming invoice for the subscription.
     */
    public function upcomingInvoice(): ?Invoice
    {
        if ($this->canceled()) {
            return null;
        }

        if (! $this->next_billing_at) {
            return null;
        }

        $billable = $this->billableModel();

        if (! $billable instanceof Model || ! $billable instanceof BillableContract) {
            return null;
        }

        $this->loadMissing('items');

        if ($this->items->isEmpty()) {
            return null;
        }

        $currency = $billable->preferredCurrency();
        $total = $this->calculateSubscriptionAmount() - (int) ($this->coupon_discount ?? 0);

        $purchase = PurchaseData::from([
            'id' => "upcoming-{$this->id}",
            'type' => 'purchase',
            'client' => [
                'email' => $billable->chipEmail(),
                'full_name' => $billable->chipName(),
            ],
            'purchase' => [
                'currency' => $currency,
                'products' => $this->items->map(fn (SubscriptionItem $item): array => [
                    'name' => $item->chip_product ?? $item->chip_price ?? 'Subscription item',
                    'price' => $item->unit_amount ?? 0,
                    'quantity' => $item->quantity ?? 1,
                    'discount' => 0,
                ])->all(),
                'total' => max(0, $total),
            ],
            'brand_id' => config('chip.collect.brand_id', ''),
            'status' => 'created',
            'reference' => "Subscription {$this->type}",
            'due' => $this->next_billing_at->timestamp,
        ]);

        return new Invoice($billable, $purchase);
    }

    /**
     * Get the latest invoice for the subscription.
     */
    public function latestInvoice(): ?Invoice
    {
        return $this->invoices()->first();
    }

    /**
     * Get a collection of the subscription's invoices.
     *
     * @return Collection<int, Invoice>
     */
    public function invoices(): Collection
    {
        $billable = $this->billableModel();

        if (! $billable instanceof Model || ! $billable instanceof BillableContract) {
            return collect();
        }

        $seenPurchaseIds = [];

        return $this->renewalAttempts()
            ->whereNotNull('purchase_id')
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (RenewalAttempt $attempt) use ($billable, &$seenPurchaseIds): ?Invoice {
                $purchase = $this->purchaseForAttempt($attempt);

                if (! $purchase instanceof PurchaseData || isset($seenPurchaseIds[$purchase->id])) {
                    return null;
                }

                $seenPurchaseIds[$purchase->id] = true;

                return new Invoice($billable, $purchase);
            })
            ->filter(fn (?Invoice $invoice): bool => $invoice instanceof Invoice)
            ->values();
    }

    /**
     * Determine if the subscription is paused.
     */
    public function paused(): bool
    {
        return $this->chip_status === SubscriptionStatus::Paused;
    }

    /**
     * Filter query by paused.
     */
    public function scopeWherePaused(Builder $query): void
    {
        $query->where('chip_status', SubscriptionStatus::Paused);
    }

    /**
     * Pause the subscription.
     *
     * @return $this
     */
    public function pause(): static
    {
        $this->forceFill([
            'chip_status' => SubscriptionStatus::Paused,
            'paused_at' => CarbonImmutable::now(),
        ])->save();

        return $this;
    }

    /**
     * Unpause the subscription.
     *
     * @return $this
     */
    public function unpause(): static
    {
        $this->forceFill([
            'chip_status' => SubscriptionStatus::Active,
            'paused_at' => null,
        ])->save();

        return $this;
    }

    /**
     * Make sure a subscription is not incomplete when performing changes.
     *
     * @throws SubscriptionUpdateFailure
     */
    public function guardAgainstIncomplete(): void
    {
        if ($this->incomplete()) {
            throw SubscriptionUpdateFailure::incompleteSubscription($this);
        }
    }

    /**
     * Make sure a price argument is provided when the subscription is a subscription with multiple prices.
     *
     * @throws InvalidArgumentException
     */
    public function guardAgainstMultiplePrices(): void
    {
        if ($this->hasMultiplePrices()) {
            throw new InvalidArgumentException(
                'This method requires a price argument since the subscription has multiple prices.'
            );
        }
    }

    /**
     * Calculate the total subscription amount based on items.
     */
    public function calculateSubscriptionAmount(): int
    {
        $this->loadMissing('items');

        return $this->items->sum(function (SubscriptionItem $item): int {
            return ($item->unit_amount ?? 0) * ($item->quantity ?? 1);
        });
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if (! (bool) config('cashier-chip.features.owner.enabled', false)) {
                return;
            }

            if (! (bool) config('cashier-chip.features.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = $subscription->resolveOwner();

            if ($owner === null) {
                return;
            }

            // Validate that the billable belongs to the subscription owner.
            if (
                (bool) config('cashier-chip.features.owner.validate_billable_owner', true)
                && is_string($subscription->billable_type)
                && $subscription->billable_type !== ''
                && $subscription->billable_id !== null
            ) {
                $billableModel = Relation::getMorphedModel($subscription->billable_type)
                    ?? $subscription->billable_type;

                if (! is_a($billableModel, Model::class, true)) {
                    throw new AuthorizationException('Cross-tenant subscription write blocked.');
                }

                // Fast-path: if owner is the billable, allow it
                if (
                    is_a($owner, $billableModel)
                    && (string) $owner->getKey() === (string) $subscription->billable_id
                ) {
                    // Safe fast-path: tenant owner is the billable.
                    // No additional owner-scoped lookup required.
                } else {
                    if (! method_exists($billableModel, 'scopeForOwner')) {
                        if (is_a($owner, $billableModel)) {
                            throw new AuthorizationException('Cross-tenant subscription write blocked.');
                        }

                        return;
                    }

                    $exists = $billableModel::forOwner($owner, false)
                        ->whereKey($subscription->billable_id)
                        ->exists();

                    if (! $exists) {
                        throw new AuthorizationException('Cross-tenant subscription write blocked.');
                    }
                }
            }

            if (! $subscription->hasOwner()) {
                $subscription->assignOwner($owner);
            }
        });

        static::creating(function (self $subscription): void {
            if ($subscription->trial_ends_at !== null && $subscription->trial_started_at === null) {
                $subscription->trial_started_at = CarbonImmutable::now();
            }
        });

        static::deleting(function (Subscription $subscription): void {
            $subscription->items()->delete();
        });
    }

    protected function resolveOwner(): ?Model
    {
        return OwnerContext::resolve();
    }

    private function billableModel(): ?Model
    {
        $this->loadMissing('billable');
        $billable = $this->getRelation('billable');

        if (! $billable instanceof Model || ! $billable instanceof BillableContract) {
            return null;
        }

        return $billable;
    }

    private function purchaseForAttempt(RenewalAttempt $attempt): ?PurchaseData
    {
        if (! is_string($attempt->purchase_id) || $attempt->purchase_id === '') {
            return null;
        }

        try {
            return Cashier::chip()->getPurchase($attempt->purchase_id);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTrustedSubscriptionItem(array $attributes): SubscriptionItem
    {
        /** @var SubscriptionItem $item */
        $item = $this->items()->make();
        $item->forceFill($attributes);
        $item->save();

        return $item;
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return SubscriptionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chip_status' => SubscriptionStatus::class,
            'ends_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'past_due_at' => 'immutable_datetime',
            'trial_started_at' => 'immutable_datetime',
            'renewed_at' => 'immutable_datetime',
            'quantity' => 'integer',
            'trial_ends_at' => 'immutable_datetime',
            'next_billing_at' => 'immutable_datetime',
            'coupon_discount' => 'integer',
            'coupon_applied_at' => 'immutable_datetime',
        ];
    }

    /**
     * Validate that a coupon can be applied to the subscription.
     *
     * @throws InvalidCoupon
     */
    protected function validateCouponForApplication(string $couponId): void
    {
        $coupon = $this->retrieveCoupon($couponId);

        if (! $coupon) {
            throw InvalidCoupon::notFound($couponId);
        }

        if (! $coupon->isValid()) {
            throw InvalidCoupon::inactive($couponId);
        }

        if ($coupon->isForeverAmountOff()) {
            throw InvalidCoupon::cannotApplyForeverAmountOffToSubscription($couponId);
        }
    }

    /**
     * Retrieve a coupon by its ID (voucher code).
     */
    protected function retrieveCoupon(string $couponId): ?Coupon
    {
        $service = VoucherIntegration::service();

        $voucherData = $service->find($couponId);

        if (! $voucherData) {
            return null;
        }

        return new Coupon($voucherData);
    }

    /**
     * Record coupon usage.
     */
    protected function recordCouponUsage(string $couponId, int $discountAmount): void
    {
        $service = VoucherIntegration::service();

        $billable = $this->billableModel();

        if (! $billable instanceof BillableContract) {
            throw new LogicException('A billable model is required to record coupon usage.');
        }

        $currency = $billable->preferredCurrency();

        $service->recordUsage(
            code: $couponId,
            discountAmount: Money::$currency($discountAmount),
            channel: 'subscription',
            metadata: ['subscription_id' => $this->id],
            redeemedBy: $billable,
        );
    }

    /**
     * Calculate when the discount ends based on duration.
     */
    protected function calculateDiscountEnd(): ?CarbonInterface
    {
        if (! $this->coupon_applied_at || ! $this->coupon_duration) {
            return null;
        }

        return match ($this->coupon_duration) {
            'once' => $this->next_billing_at,
            'forever' => null,
            'repeating' => $this->coupon_applied_at->copy()->addMonths(
                $this->retrieveCoupon($this->coupon_id)?->durationInMonths() ?? 1
            ),
            default => null,
        };
    }

    /**
     * Calculate the current status based on subscription state.
     */
    protected function calculateCurrentStatus(): SubscriptionStatus
    {
        if ($this->ended()) {
            return SubscriptionStatus::Canceled;
        }

        if ($this->onTrial()) {
            return SubscriptionStatus::Trialing;
        }

        if ($this->onGracePeriod()) {
            return SubscriptionStatus::Canceled;
        }

        return SubscriptionStatus::Active;
    }
}
