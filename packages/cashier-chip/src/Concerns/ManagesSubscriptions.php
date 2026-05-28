<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionBuilder;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait ManagesSubscriptions // @phpstan-ignore trait.unused
{
    /**
     * Get the model-level generic trial end timestamp, if the billable model supports it.
     */
    private function genericTrialEndsAtValue(): ?Carbon
    {
        if (! $this->supportsGenericTrialAttribute()) {
            return null;
        }

        $trialEndsAt = $this->getAttribute('trial_ends_at');

        if ($trialEndsAt instanceof Carbon) {
            return $trialEndsAt;
        }

        if ($trialEndsAt instanceof DateTimeInterface) {
            return Carbon::instance($trialEndsAt);
        }

        if (is_string($trialEndsAt) && $trialEndsAt !== '') {
            return Carbon::parse($trialEndsAt);
        }

        return null;
    }

    /**
     * Determine whether the underlying model can safely expose a generic trial attribute.
     */
    private function supportsGenericTrialAttribute(): bool
    {
        return array_key_exists('trial_ends_at', $this->getAttributes())
            || array_key_exists('trial_ends_at', $this->getCasts())
            || $this->hasGetMutator('trial_ends_at')
            || $this->hasAttributeGetMutator('trial_ends_at');
    }

    /**
     * Begin creating a new subscription.
     *
     * @param  string|array<string>  $prices
     */
    public function newSubscription(string $type, string | array $prices = []): SubscriptionBuilder
    {
        return new SubscriptionBuilder($this, $type, $prices);
    }

    /**
     * Determine if the CHIP model is on trial.
     */
    public function onTrial(string $type = 'default', ?string $price = null): bool
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->onTrial()) {
            return false;
        }

        return ! $price || $subscription->hasPrice($price);
    }

    /**
     * Determine if the CHIP model's trial has ended.
     */
    public function hasExpiredTrial(string $type = 'default', ?string $price = null): bool
    {
        if (func_num_args() === 0 && $this->hasExpiredGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->hasExpiredTrial()) {
            return false;
        }

        return ! $price || $subscription->hasPrice($price);
    }

    /**
     * Determine if the CHIP model is on a "generic" trial at the model level.
     */
    public function onGenericTrial(): bool
    {
        return $this->genericTrialEndsAtValue()?->isFuture() ?? false;
    }

    /**
     * Filter the given query for generic trials.
     */
    public function scopeOnGenericTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Determine if the CHIP model's "generic" trial at the model level has expired.
     */
    public function hasExpiredGenericTrial(): bool
    {
        return $this->genericTrialEndsAtValue()?->isPast() ?? false;
    }

    /**
     * Filter the given query for expired generic trials.
     */
    public function scopeHasExpiredGenericTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', Carbon::now());
    }

    /**
     * Get the ending date of the trial.
     *
     * @return \Illuminate\Support\Carbon|null
     */
    public function trialEndsAt(string $type = 'default')
    {
        $genericTrialEndsAt = $this->genericTrialEndsAtValue();

        if (func_num_args() === 0 && $genericTrialEndsAt?->isFuture()) {
            return $genericTrialEndsAt;
        }

        if ($subscription = $this->subscription($type)) {
            return $subscription->trial_ends_at;
        }

        return $genericTrialEndsAt;
    }

    /**
     * Determine if the CHIP model has a given subscription.
     */
    public function subscribed(string $type = 'default', ?string $price = null): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return ! $price || $subscription->hasPrice($price);
    }

    /**
     * Get a subscription instance by $type.
     */
    public function subscription(string $type = 'default'): ?Subscription
    {
        return $this->subscriptions->where('type', $type)->first();
    }

    /**
     * Get all of the subscriptions for the CHIP model.
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Cashier::$subscriptionModel, 'billable')->orderBy('created_at', 'desc');
    }

    /**
     * Determine if the customer's subscription has an incomplete payment.
     */
    public function hasIncompletePayment(string $type = 'default'): bool
    {
        if ($subscription = $this->subscription($type)) {
            return $subscription->hasIncompletePayment();
        }

        return false;
    }

    /**
     * Determine if the CHIP model is actively subscribed to one of the given products.
     *
     * @param  string|array<string>  $products
     */
    public function subscribedToProduct(string | array $products, string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $products as $product) {
            if ($subscription->hasProduct($product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the CHIP model is actively subscribed to one of the given prices.
     *
     * @param  string|array<string>  $prices
     */
    public function subscribedToPrice(string | array $prices, string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $prices as $price) {
            if ($subscription->hasPrice($price)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the customer has a valid subscription on the given product.
     */
    public function onProduct(string $product): bool
    {
        return ! is_null($this->subscriptions->first(function (Subscription $subscription) use ($product) {
            return $subscription->valid() && $subscription->hasProduct($product);
        }));
    }

    /**
     * Determine if the customer has a valid subscription on the given price.
     */
    public function onPrice(string $price): bool
    {
        return ! is_null($this->subscriptions->first(function (Subscription $subscription) use ($price) {
            return $subscription->valid() && $subscription->hasPrice($price);
        }));
    }

    /**
     * Get the tax rates to apply to the subscription.
     *
     * @return array<int, string>
     */
    public function taxRates(): array
    {
        return [];
    }

    /**
     * Get the tax rates to apply to individual subscription items.
     *
     * @return array<string, array<int, string>>
     */
    public function priceTaxRates(): array
    {
        return [];
    }
}
