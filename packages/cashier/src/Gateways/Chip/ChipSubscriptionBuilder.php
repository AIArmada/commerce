<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\CashierChip\SubscriptionBuilder;
use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Wrapper for CHIP subscription builder.
 */
class ChipSubscriptionBuilder implements SubscriptionBuilderContract
{
    /**
     * The underlying subscription builder.
     */
    protected SubscriptionBuilder $builder;

    /**
     * Create a new CHIP subscription builder.
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
     * Note: CHIP doesn't have native coupon support, this is for compatibility.
     */
    public function coupon(string $coupon): static
    {
        // CHIP doesn't support coupons natively
        // Could be implemented via metadata or local discount tracking
        return $this;
    }

    /**
     * Apply a promotion code.
     * Note: CHIP doesn't have native promotion code support.
     */
    public function promotionCode(string $promotionCode): static
    {
        // CHIP doesn't support promotion codes natively
        return $this;
    }

    /**
     * Allow promotion codes during checkout.
     * Note: CHIP doesn't have native promotion code support.
     */
    public function allowPromotionCodes(bool $allow = true): static
    {
        // CHIP doesn't support promotion codes natively
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
        // CHIP-specific method - could be extended
        return $this;
    }

    /**
     * Set the billing interval.
     */
    public function billingInterval(string $interval): static
    {
        $this->builder->billingInterval($interval);

        return $this;
    }

    /**
     * Create the subscription.
     */
    public function create(?string $paymentMethod = null, array $options = []): SubscriptionContract
    {
        $subscription = $this->builder->create($paymentMethod, $options);

        return new ChipSubscription($subscription);
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
