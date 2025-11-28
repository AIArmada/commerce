<?php

namespace AIArmada\CashierChip;

use Illuminate\Support\Traits\Conditionable;
use AIArmada\Chip\Facades\ChipCollect;

/**
 * Fluent builder for creating CHIP checkout sessions.
 */
class CheckoutBuilder
{
    use Conditionable;

    /**
     * The model that is checking out.
     *
     * @var \AIArmada\CashierChip\Billable|\Illuminate\Database\Eloquent\Model|null
     */
    protected $owner;

    /**
     * Whether to request a recurring token.
     *
     * @var bool
     */
    protected bool $recurring = false;

    /**
     * The success URL.
     *
     * @var string|null
     */
    protected ?string $successUrl = null;

    /**
     * The cancel URL.
     *
     * @var string|null
     */
    protected ?string $cancelUrl = null;

    /**
     * The webhook URL.
     *
     * @var string|null
     */
    protected ?string $webhookUrl = null;

    /**
     * The metadata for the checkout session.
     *
     * @var array
     */
    protected array $metadata = [];

    /**
     * The products for the checkout session.
     *
     * @var array
     */
    protected array $products = [];

    /**
     * The currency for the checkout.
     *
     * @var string|null
     */
    protected ?string $currency = null;

    /**
     * Create a new checkout builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @return void
     */
    public function __construct($owner = null)
    {
        $this->owner = $owner;
    }

    /**
     * Request a recurring token for future payments.
     *
     * @param  bool  $recurring
     * @return $this
     */
    public function recurring(bool $recurring = true)
    {
        $this->recurring = $recurring;

        return $this;
    }

    /**
     * Set the success URL.
     *
     * @param  string  $url
     * @return $this
     */
    public function successUrl(string $url)
    {
        $this->successUrl = $url;

        return $this;
    }

    /**
     * Set the cancel URL.
     *
     * @param  string  $url
     * @return $this
     */
    public function cancelUrl(string $url)
    {
        $this->cancelUrl = $url;

        return $this;
    }

    /**
     * Set the webhook URL.
     *
     * @param  string  $url
     * @return $this
     */
    public function webhookUrl(string $url)
    {
        $this->webhookUrl = $url;

        return $this;
    }

    /**
     * Set the metadata for the checkout session.
     *
     * @param  array  $metadata
     * @return $this
     */
    public function withMetadata(array $metadata)
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Add a product to the checkout.
     *
     * @param  string  $name
     * @param  int  $price  Price in cents
     * @param  int  $quantity
     * @return $this
     */
    public function addProduct(string $name, int $price, int $quantity = 1)
    {
        $this->products[] = [
            'name' => $name,
            'price' => $price / 100, // Convert to decimal for CHIP
            'quantity' => $quantity,
        ];

        return $this;
    }

    /**
     * Set the products for checkout.
     *
     * @param  array  $products
     * @return $this
     */
    public function products(array $products)
    {
        $this->products = $products;

        return $this;
    }

    /**
     * Set the currency.
     *
     * @param  string  $currency
     * @return $this
     */
    public function currency(string $currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Create the checkout session.
     *
     * @param  int  $amount  Amount in cents
     * @param  array  $options
     * @return \AIArmada\CashierChip\Checkout
     */
    public function create(int $amount, array $options = []): Checkout
    {
        $options = array_merge([
            'recurring' => $this->recurring,
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
            'webhook_url' => $this->webhookUrl,
            'metadata' => $this->metadata,
            'products' => $this->products ?: null,
            'currency' => $this->currency,
        ], $options);

        // Remove null values
        $options = array_filter($options, fn($value) => ! is_null($value));

        return Checkout::create($this->owner, $amount, $options);
    }

    /**
     * Create a checkout for a single charge.
     *
     * @param  int  $amount  Amount in cents
     * @param  string  $description
     * @param  array  $options
     * @return \AIArmada\CashierChip\Checkout
     */
    public function charge(int $amount, string $description = 'Payment', array $options = []): Checkout
    {
        return $this->create($amount, array_merge([
            'reference' => $description,
        ], $options));
    }
}
