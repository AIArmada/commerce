<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Stripe;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Laravel\Cashier\SubscriptionBuilder;

/**
 * Wrapper for Stripe subscription builder.
 *
 * This class wraps the Laravel Cashier SubscriptionBuilder and adapts it
 * to the unified SubscriptionBuilderContract interface.
 */
class StripeSubscriptionBuilder implements SubscriptionBuilderContract
{
    /**
     * The underlying subscription builder.
     *
     * @var SubscriptionBuilder
     */
    protected $builder;

    /**
     * Local tracking of items for getItems().
     *
     * @var array<string, mixed>
     */
    protected array $items = [];

    /**
     * Local tracking of metadata.
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Local tracking of trial end.
     */
    protected ?CarbonInterface $trialEnd = null;

    /**
     * Create a new Stripe subscription builder.
     *
     * @param  string|array<string>  $prices
     */
    public function __construct(
        protected BillableContract $billable,
        protected string $type,
        string | array $prices = []
    ) {
        // Directly instantiate the native SubscriptionBuilder to avoid
        // conflicts with the unified cashier package's method overrides
        $this->builder = new SubscriptionBuilder($billable, $type, $prices);

        // Track initial prices
        foreach ((array) $prices as $price) {
            $this->items[$price] = ['price' => $price, 'quantity' => 1];
        }
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'stripe';
    }

    /**
     * Set the price for the subscription.
     */
    public function price(string | array $price, ?int $quantity = 1): self
    {
        $priceId = is_array($price) ? ($price['price'] ?? $price[0]) : $price;
        $qty = is_array($price) ? ($price['quantity'] ?? $quantity) : $quantity;

        $this->builder->price($priceId, $qty);
        $this->items[$priceId] = ['price' => $priceId, 'quantity' => $qty];

        return $this;
    }

    /**
     * Set the quantity of the subscription.
     */
    public function quantity(?int $quantity, ?string $price = null): self
    {
        $this->builder->quantity($quantity, $price);

        if ($price && isset($this->items[$price])) {
            $this->items[$price]['quantity'] = $quantity;
        }

        return $this;
    }

    /**
     * Set the trial days.
     */
    public function trialDays(int $trialDays): self
    {
        $this->builder->trialDays($trialDays);
        $this->trialEnd = Carbon::now()->addDays($trialDays);

        return $this;
    }

    /**
     * Set the trial end date.
     */
    public function trialUntil(CarbonInterface $trialUntil): self
    {
        $this->builder->trialUntil($trialUntil);
        $this->trialEnd = $trialUntil;

        return $this;
    }

    /**
     * Skip the trial period.
     */
    public function skipTrial(): self
    {
        $this->builder->skipTrial();
        $this->trialEnd = null;

        return $this;
    }

    /**
     * Set the billing interval.
     */
    public function billingInterval(string $interval, int $count = 1): self
    {
        // Stripe handles billing interval via the price, not the subscription builder
        // This is a no-op for Stripe but required by the contract
        return $this;
    }

    /**
     * Set monthly billing.
     */
    public function monthly(int $count = 1): self
    {
        return $this->billingInterval('month', $count);
    }

    /**
     * Set yearly billing.
     */
    public function yearly(int $count = 1): self
    {
        return $this->billingInterval('year', $count);
    }

    /**
     * Set weekly billing.
     */
    public function weekly(int $count = 1): self
    {
        return $this->billingInterval('week', $count);
    }

    /**
     * Set daily billing.
     */
    public function daily(int $count = 1): self
    {
        return $this->billingInterval('day', $count);
    }

    /**
     * Anchor the billing cycle to a date.
     */
    public function anchorBillingCycleOn(DateTimeInterface | CarbonInterface $date): self
    {
        if ($date instanceof DateTimeInterface && ! $date instanceof CarbonInterface) {
            $date = Carbon::instance($date);
        }

        $this->builder->anchorBillingCycleOn($date);

        return $this;
    }

    /**
     * Apply a coupon.
     */
    public function withCoupon(?string $coupon): self
    {
        if ($coupon) {
            $this->builder->withCoupon($coupon);
        }

        return $this;
    }

    /**
     * Apply a promotion code.
     */
    public function withPromotionCode(?string $promotionCode): self
    {
        if ($promotionCode) {
            $this->builder->withPromotionCode($promotionCode);
        }

        return $this;
    }

    /**
     * Set metadata on the subscription.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->builder->withMetadata($metadata);
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Allow incomplete payments / payment failures.
     */
    public function allowPaymentFailures(): self
    {
        if (method_exists($this->builder, 'allowPaymentFailures')) {
            $this->builder->allowPaymentFailures();
        }

        return $this;
    }

    /**
     * Add a new subscription without immediate payment.
     *
     * @param  array<string, mixed>  $options
     */
    public function add(array $options = []): SubscriptionContract
    {
        $subscription = $this->builder->add($options);

        return new StripeSubscription($subscription);
    }

    /**
     * Create the subscription with payment.
     *
     * @param  array<string, mixed>  $options
     */
    public function create(?string $paymentMethod = null, array $options = []): SubscriptionContract
    {
        $subscription = $this->builder->create($paymentMethod, $options);

        return new StripeSubscription($subscription);
    }

    /**
     * Create a checkout session for the subscription.
     *
     * @param  array<string, mixed>  $sessionOptions
     */
    public function checkout(array $sessionOptions = []): CheckoutContract
    {
        $checkout = $this->builder->checkout($sessionOptions);

        return new StripeCheckout($checkout->asStripeCheckoutSession());
    }

    /**
     * Get the subscription type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the items/prices on the builder.
     *
     * @return array<string, mixed>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get the trial end date.
     */
    public function getTrialEnd(): ?CarbonInterface
    {
        return $this->trialEnd;
    }

    /**
     * Get the metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the underlying builder.
     *
     * @return SubscriptionBuilder
     */
    public function asGatewayBuilder()
    {
        return $this->builder;
    }
}
