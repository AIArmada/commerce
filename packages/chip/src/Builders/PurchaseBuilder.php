<?php

declare(strict_types=1);

namespace AIArmada\Chip\Builders;

use AIArmada\Chip\DataObjects\Purchase;
use AIArmada\Chip\Services\ChipCollectService;

final class PurchaseBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(
        private ChipCollectService $service
    ) {}

    /**
     * Set the brand ID
     */
    public function brand(string $brandId): self
    {
        $this->data['brand_id'] = $brandId;

        return $this;
    }

    /**
     * Set purchase currency
     */
    public function currency(string $currency = 'MYR'): self
    {
        $this->data['purchase']['currency'] = $currency;

        return $this;
    }

    /**
     * Add a product to the purchase
     */
    public function addProduct(
        string $name,
        int $price,
        string|float|int $quantity = 1,
        int $discount = 0,
        float $taxPercent = 0,
        ?string $category = null
    ): self {
        $product = [
            'name' => $name,
            'price' => $price,
            'quantity' => (string) $quantity,
        ];

        if ($discount > 0) {
            $product['discount'] = $discount;
        }

        if ($taxPercent > 0) {
            $product['tax_percent'] = $taxPercent;
        }

        if ($category !== null) {
            $product['category'] = $category;
        }

        $this->data['purchase']['products'][] = $product;

        return $this;
    }

    /**
     * Set customer email
     */
    public function email(string $email): self
    {
        $this->data['client']['email'] = $email;

        return $this;
    }

    /**
     * Set customer details
     */
    public function customer(
        string $email,
        ?string $fullName = null,
        ?string $phone = null,
        ?string $country = null
    ): self {
        $this->data['client']['email'] = $email;

        if ($fullName !== null) {
            $this->data['client']['full_name'] = $fullName;
        }

        if ($phone !== null) {
            $this->data['client']['phone'] = $phone;
        }

        if ($country !== null) {
            $this->data['client']['country'] = $country;
        }

        return $this;
    }

    /**
     * Set existing client ID
     */
    public function clientId(string $clientId): self
    {
        $this->data['client_id'] = $clientId;
        unset($this->data['client']);

        return $this;
    }

    /**
     * Set billing address
     */
    public function billingAddress(
        string $streetAddress,
        string $city,
        string $zipCode,
        ?string $state = null,
        ?string $country = null
    ): self {
        $this->data['client']['street_address'] = $streetAddress;
        $this->data['client']['city'] = $city;
        $this->data['client']['zip_code'] = $zipCode;

        if ($state !== null) {
            $this->data['client']['state'] = $state;
        }

        if ($country !== null) {
            $this->data['client']['country'] = $country;
        }

        return $this;
    }

    /**
     * Set shipping address
     */
    public function shippingAddress(
        string $streetAddress,
        string $city,
        string $zipCode,
        ?string $state = null,
        ?string $country = null
    ): self {
        $this->data['client']['shipping_street_address'] = $streetAddress;
        $this->data['client']['shipping_city'] = $city;
        $this->data['client']['shipping_zip_code'] = $zipCode;

        if ($state !== null) {
            $this->data['client']['shipping_state'] = $state;
        }

        if ($country !== null) {
            $this->data['client']['shipping_country'] = $country;
        }

        return $this;
    }

    /**
     * Set merchant reference
     */
    public function reference(string $reference): self
    {
        $this->data['reference'] = $reference;

        return $this;
    }

    /**
     * Set success redirect URL
     */
    public function successUrl(string $url): self
    {
        $this->data['success_redirect'] = $url;

        return $this;
    }

    /**
     * Set failure redirect URL
     */
    public function failureUrl(string $url): self
    {
        $this->data['failure_redirect'] = $url;

        return $this;
    }

    /**
     * Set cancel redirect URL
     */
    public function cancelUrl(string $url): self
    {
        $this->data['cancel_redirect'] = $url;

        return $this;
    }

    /**
     * Set all redirect URLs at once
     */
    public function redirects(string $successUrl, ?string $failureUrl = null, ?string $cancelUrl = null): self
    {
        $this->successUrl($successUrl);

        if ($failureUrl !== null) {
            $this->failureUrl($failureUrl);
        }

        if ($cancelUrl !== null) {
            $this->cancelUrl($cancelUrl);
        }

        return $this;
    }

    /**
     * Set webhook callback URL
     */
    public function webhook(string $url): self
    {
        $this->data['success_callback'] = $url;

        return $this;
    }

    /**
     * Enable email receipt
     */
    public function sendReceipt(bool $send = true): self
    {
        $this->data['send_receipt'] = $send;

        return $this;
    }

    /**
     * Enable pre-authorization (skip capture)
     */
    public function preAuthorize(bool $skipCapture = true): self
    {
        $this->data['skip_capture'] = $skipCapture;

        return $this;
    }

    /**
     * Force recurring token creation
     */
    public function forceRecurring(bool $force = true): self
    {
        $this->data['force_recurring'] = $force;

        return $this;
    }

    /**
     * Set due date
     */
    public function due(int $timestamp, bool $strict = false): self
    {
        $this->data['due'] = $timestamp;
        $this->data['due_strict'] = $strict;

        return $this;
    }

    /**
     * Add notes to the purchase
     */
    public function notes(string $notes): self
    {
        $this->data['purchase']['notes'] = $notes;

        return $this;
    }

    /**
     * Get the built data array (for inspection)
     */
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Create the purchase
     */
    public function create(): Purchase
    {
        // Use brand_id from config if not set
        if (! isset($this->data['brand_id'])) {
            $this->data['brand_id'] = config('chip.collect.brand_id');
        }

        return $this->service->createPurchase($this->data);
    }

    /**
     * Alias for create()
     */
    public function save(): Purchase
    {
        return $this->create();
    }
}
