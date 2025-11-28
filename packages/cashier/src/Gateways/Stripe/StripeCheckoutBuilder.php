<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Stripe;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutBuilderContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Gateways\StripeGateway;
use Illuminate\Http\RedirectResponse;

/**
 * Builder for Stripe checkout sessions.
 */
class StripeCheckoutBuilder implements CheckoutBuilderContract
{
    /**
     * The billable model.
     */
    protected ?BillableContract $billable = null;

    /**
     * The prices for the checkout.
     *
     * @var array<string, int>
     */
    protected array $lineItems = [];

    /**
     * The success URL.
     */
    protected ?string $success = null;

    /**
     * The cancel URL.
     */
    protected ?string $cancel = null;

    /**
     * The checkout mode.
     */
    protected string $checkoutMode = 'payment';

    /**
     * The coupon to apply.
     */
    protected ?string $couponCode = null;

    /**
     * Whether to allow promotion codes.
     */
    protected bool $allowPromos = false;

    /**
     * Whether to collect tax IDs.
     */
    protected bool $collectTax = false;

    /**
     * The metadata.
     *
     * @var array<string, mixed>
     */
    protected array $meta = [];

    /**
     * The trial days.
     */
    protected ?int $trial = null;

    /**
     * Create a new checkout builder.
     */
    public function __construct(
        protected StripeGateway $gateway,
        ?BillableContract $customer = null
    ) {
        $this->billable = $customer;
    }

    /**
     * Set the customer for the checkout.
     */
    public function customer(BillableContract $customer): static
    {
        $this->billable = $customer;

        return $this;
    }

    /**
     * Add a price/product to the checkout.
     */
    public function price(string $price, int $quantity = 1): static
    {
        $this->lineItems[$price] = $quantity;

        return $this;
    }

    /**
     * Add multiple prices/products to the checkout.
     *
     * @param  array<string, int>  $prices
     */
    public function prices(array $prices): static
    {
        foreach ($prices as $price => $quantity) {
            $this->price($price, $quantity);
        }

        return $this;
    }

    /**
     * Set the success URL.
     */
    public function successUrl(string $url): static
    {
        $this->success = $url;

        return $this;
    }

    /**
     * Set the cancel URL.
     */
    public function cancelUrl(string $url): static
    {
        $this->cancel = $url;

        return $this;
    }

    /**
     * Set the mode (payment, subscription, setup).
     */
    public function mode(string $mode): static
    {
        $this->checkoutMode = $mode;

        return $this;
    }

    /**
     * Apply a coupon or promotion code.
     */
    public function coupon(string $coupon): static
    {
        $this->couponCode = $coupon;

        return $this;
    }

    /**
     * Allow promotion codes.
     */
    public function allowPromotionCodes(bool $allow = true): static
    {
        $this->allowPromos = $allow;

        return $this;
    }

    /**
     * Collect tax ID from the customer.
     */
    public function collectTaxIds(bool $collect = true): static
    {
        $this->collectTax = $collect;

        return $this;
    }

    /**
     * Set metadata for the checkout.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static
    {
        $this->meta = $metadata;

        return $this;
    }

    /**
     * Set the trial period in days.
     */
    public function trialDays(int $days): static
    {
        $this->trial = $days;

        return $this;
    }

    /**
     * Create the checkout session.
     */
    public function create(): CheckoutContract
    {
        $lineItems = collect($this->lineItems)->map(function ($quantity, $price) {
            return [
                'price' => $price,
                'quantity' => $quantity,
            ];
        })->values()->all();

        $options = [
            'mode' => $this->checkoutMode,
            'line_items' => $lineItems,
            'success_url' => $this->success ?? url('/'),
            'cancel_url' => $this->cancel ?? url('/'),
        ];

        if ($this->billable && $this->billable->stripeId()) {
            $options['customer'] = $this->billable->stripeId();
        }

        if ($this->couponCode) {
            $options['discounts'] = [['coupon' => $this->couponCode]];
        }

        if ($this->allowPromos) {
            $options['allow_promotion_codes'] = true;
        }

        if ($this->collectTax) {
            $options['tax_id_collection'] = ['enabled' => true];
        }

        if (! empty($this->meta)) {
            $options['metadata'] = $this->meta;
        }

        if ($this->trial && $this->checkoutMode === 'subscription') {
            $options['subscription_data'] = [
                'trial_period_days' => $this->trial,
            ];
        }

        $session = $this->gateway->client()->checkout->sessions->create($options);

        return new StripeCheckout($session);
    }

    /**
     * Create and redirect to the checkout session.
     */
    public function redirect(): RedirectResponse
    {
        $checkout = $this->create();

        return redirect()->to($checkout->url());
    }
}
