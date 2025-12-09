<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Contract for subscription builders.
 */
interface SubscriptionBuilderContract
{
    /**
     * Get the gateway for this builder.
     */
    public function gateway(): string;

    /**
     * Set a price on the subscription.
     */
    public function price(string | array $price, ?int $quantity = 1): self;

    /**
     * Set the quantity.
     */
    public function quantity(?int $quantity, ?string $price = null): self;

    /**
     * Set the trial days.
     */
    public function trialDays(int $trialDays): self;

    /**
     * Set the trial end date.
     */
    public function trialUntil(CarbonInterface $trialUntil): self;

    /**
     * Skip the trial.
     */
    public function skipTrial(): self;

    /**
     * Set the billing interval.
     */
    public function billingInterval(string $interval, int $count = 1): self;

    /**
     * Set monthly billing.
     */
    public function monthly(int $count = 1): self;

    /**
     * Set yearly billing.
     */
    public function yearly(int $count = 1): self;

    /**
     * Set weekly billing.
     */
    public function weekly(int $count = 1): self;

    /**
     * Set daily billing.
     */
    public function daily(int $count = 1): self;

    /**
     * Anchor the billing cycle to a date.
     */
    public function anchorBillingCycleOn(DateTimeInterface | CarbonInterface $date): self;

    /**
     * Set the coupon/promotion code.
     */
    public function withCoupon(?string $coupon): self;

    /**
     * Set the promotion code.
     */
    public function withPromotionCode(?string $promotionCode): self;

    /**
     * Set metadata on the subscription.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self;

    /**
     * Allow incomplete payments.
     */
    public function allowPaymentFailures(): self;

    /**
     * Add the subscription to the database without payment.
     *
     * @param  array<string, mixed>  $options
     */
    public function add(array $options = []): SubscriptionContract;

    /**
     * Create the subscription with payment.
     *
     * @param  array<string, mixed>  $options
     */
    public function create(?string $paymentMethod = null, array $options = []): SubscriptionContract;

    /**
     * Create a checkout session for the subscription.
     *
     * @param  array<string, mixed>  $sessionOptions
     */
    public function checkout(array $sessionOptions = []): CheckoutContract;

    /**
     * Get the subscription type.
     */
    public function getType(): string;

    /**
     * Get the items/prices on the builder.
     *
     * @return array<string, mixed>
     */
    public function getItems(): array;

    /**
     * Get the trial end date.
     */
    public function getTrialEnd(): ?CarbonInterface;

    /**
     * Get the metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;
}
