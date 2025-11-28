<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Stripe;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Laravel\Cashier\SubscriptionBuilder;

/**
 * Wrapper for Stripe subscription builder.
 */
class StripeSubscriptionBuilder implements SubscriptionBuilderContract
{
    /**
     * The underlying subscription builder.
     */
    protected SubscriptionBuilder $builder;

    /**
     * Create a new Stripe subscription builder.
     *
     * @param  string|array<string>  $prices
     */
    public function __construct(
        protected BillableContract $billable,
        protected string $type,
        string|array $prices = []
    ) {
        $this->builder = $billable->newSubscription($type, $prices);
    }

    /**
     * Set the price for the subscription.
     */
    public function price(string $price): static
    {
        $this->builder->price($price);

        return $this;
    }

    /**
     * Set multiple prices for the subscription.
     *
     * @param  array<string>  $prices
     */
    public function prices(array $prices): static
    {
        foreach ($prices as $price) {
            $this->builder->price($price);
        }

        return $this;
    }

    /**
     * Set the quantity of the subscription.
     */
    public function quantity(int $quantity, ?string $price = null): static
    {
        $this->builder->quantity($quantity, $price);

        return $this;
    }

    /**
     * Set the trial days.
     */
    public function trialDays(int $days): static
    {
        $this->builder->trialDays($days);

        return $this;
    }

    /**
     * Set the trial end date.
     */
    public function trialUntil(CarbonInterface|DateTimeInterface $date): static
    {
        $this->builder->trialUntil($date);

        return $this;
    }

    /**
     * Skip the trial period.
     */
    public function skipTrial(): static
    {
        $this->builder->skipTrial();

        return $this;
    }

    /**
     * Apply a coupon.
     */
    public function coupon(string $coupon): static
    {
        $this->builder->withCoupon($coupon);

        return $this;
    }

    /**
     * Apply a promotion code.
     */
    public function promotionCode(string $promotionCode): static
    {
        $this->builder->withPromotionCode($promotionCode);

        return $this;
    }

    /**
     * Allow promotion codes during checkout.
     */
    public function allowPromotionCodes(bool $allow = true): static
    {
        $this->builder->allowPromotionCodes($allow);

        return $this;
    }

    /**
     * Set metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static
    {
        $this->builder->withMetadata($metadata);

        return $this;
    }

    /**
     * Anchor the billing cycle.
     */
    public function anchorBillingCycleOn(int $day): static
    {
        $this->builder->anchorBillingCycleOn($day);

        return $this;
    }

    /**
     * Set the payment behavior.
     */
    public function paymentBehavior(string $behavior): static
    {
        // Stripe-specific method
        return $this;
    }

    /**
     * Create the subscription.
     */
    public function create(?string $paymentMethod = null, array $options = []): SubscriptionContract
    {
        $subscription = $this->builder->create($paymentMethod, $options);

        return new StripeSubscription($subscription);
    }

    /**
     * Create a checkout session for the subscription.
     */
    public function checkout(array $options = []): mixed
    {
        return $this->builder->checkout($options);
    }

    /**
     * Get the underlying builder.
     */
    public function asGatewayBuilder(): SubscriptionBuilder
    {
        return $this->builder;
    }
}
