<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutBuilderContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Gateways\ChipGateway;
use Illuminate\Http\RedirectResponse;

/**
 * Builder for CHIP checkout sessions (purchases).
 *
 * This class provides compatibility with the unified cashier interface while
 * delegating to the native cashier-chip CheckoutBuilder when available.
 */
class ChipCheckoutBuilder implements CheckoutBuilderContract
{
    /**
     * The billable model.
     */
    protected ?BillableContract $billable = null;

    /**
     * The products for the checkout.
     *
     * @var array<array<string, mixed>>
     */
    protected array $products = [];

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
     * The coupon code.
     */
    protected ?string $couponCode = null;

    /**
     * Whether to allow promotion codes.
     */
    protected bool $allowPromos = false;

    /**
     * Create a new checkout builder.
     */
    public function __construct(
        protected ChipGateway $gateway,
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
        // For CHIP, price should contain amount in format "name:amount"
        // or just use the price as product name with a default amount
        $this->products[] = [
            'name' => $price,
            'quantity' => $quantity,
            'price' => 0, // Should be set via product() method
        ];

        return $this;
    }

    /**
     * Add a product with name and price.
     */
    public function product(string $name, int $priceInCents, int $quantity = 1): static
    {
        $this->products[] = [
            'name' => $name,
            'quantity' => $quantity,
            'price' => $priceInCents,
        ];

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
     *
     * Uses the vouchers package integration in cashier-chip.
     */
    public function coupon(string $coupon): static
    {
        $this->couponCode = $coupon;
        $this->meta['coupon_id'] = $coupon;

        return $this;
    }

    /**
     * Allow promotion codes.
     *
     * Uses the vouchers package integration in cashier-chip.
     */
    public function allowPromotionCodes(bool $allow = true): static
    {
        $this->allowPromos = $allow;
        $this->meta['allow_promotion_codes'] = $allow;

        return $this;
    }

    /**
     * Collect tax ID from the customer.
     * Note: CHIP handles tax differently.
     */
    public function collectTaxIds(bool $collect = true): static
    {
        // CHIP handles tax collection differently
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
     * Force recurring token collection.
     */
    public function forRecurring(bool $force = true): static
    {
        $this->meta['force_recurring'] = $force;

        return $this;
    }

    /**
     * Skip capture (for trials or payment method collection).
     */
    public function skipCapture(bool $skip = true): static
    {
        $this->meta['skip_capture'] = $skip;

        return $this;
    }

    /**
     * Create the checkout session (purchase).
     */
    public function create(): CheckoutContract
    {
        $options = [
            'purchase' => [
                'products' => $this->products,
            ],
            'success_callback' => $this->success ?? url('/'),
            'failure_callback' => $this->cancel ?? url('/'),
            'cancel_callback' => $this->cancel ?? url('/'),
            'brand_id' => $this->gateway->brandId(),
            'metadata' => [],
        ];

        // Check for CHIP customer ID using the unified gateway interface
        if ($this->billable && $this->billable->hasGatewayId('chip')) {
            $options['client_id'] = $this->billable->gatewayId('chip');
        } elseif ($this->billable) {
            $options['client'] = [
                'email' => $this->billable->customerEmail(),
                'full_name' => $this->billable->customerName() ?? $this->billable->customerEmail(),
            ];
        }

        if (isset($this->meta['force_recurring']) && $this->meta['force_recurring']) {
            $options['force_recurring'] = true;
        }

        if (isset($this->meta['skip_capture']) && $this->meta['skip_capture']) {
            $options['skip_capture'] = true;
        }

        // For trial/setup mode, use zero amount with skip_capture
        if ($this->checkoutMode === 'setup' || ($this->trial && $this->checkoutMode === 'subscription')) {
            $options['purchase']['products'] = [
                [
                    'name' => 'Payment Method Setup',
                    'price' => 0,
                    'quantity' => 1,
                ],
            ];
            $options['skip_capture'] = true;
            $options['force_recurring'] = true;
        }

        // Add coupon metadata if present
        if ($this->couponCode) {
            $options['metadata'] = array_merge($options['metadata'], [
                'coupon_id' => $this->couponCode,
            ]);
        }

        $purchase = $this->gateway->client()->createPurchase($options);

        return new ChipCheckout($purchase);
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
