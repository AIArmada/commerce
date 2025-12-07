<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Chip\Data\Client;

/**
 * Wrapper for CHIP customer (client).
 */
class ChipCustomer implements CustomerContract
{
    /**
     * The underlying CHIP client.
     */
    protected ?Client $client = null;

    /**
     * Create a new CHIP customer wrapper.
     */
    public function __construct(
        protected BillableContract $billable,
        ?Client $client = null
    ) {
        $this->client = $client;
    }

    /**
     * Get the customer ID.
     */
    public function id(): string
    {
        return $this->client?->id ?? $this->billable->chipId() ?? '';
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'chip';
    }

    /**
     * Get the customer email.
     */
    public function email(): ?string
    {
        return $this->client?->email ?? $this->billable->chipEmail();
    }

    /**
     * Get the customer name.
     */
    public function name(): ?string
    {
        return $this->client?->fullName ?? $this->client?->legalName ?? $this->billable->chipName();
    }

    /**
     * Get the customer phone.
     */
    public function phone(): ?string
    {
        return $this->client?->phone ?? $this->billable->chipPhone();
    }

    /**
     * Get the customer address.
     *
     * @return array<string, mixed>|null
     */
    public function address(): ?array
    {
        if ($this->client?->streetAddress) {
            return [
                'line1' => $this->client->streetAddress,
                'line2' => $this->client->streetAddress2 ?? null,
                'city' => $this->client->city,
                'state' => $this->client->stateOrProvince ?? null,
                'postal_code' => $this->client->zipCode,
                'country' => $this->client->country,
            ];
        }

        $address = $this->billable->chipAddress();

        return ! empty($address) ? $address : null;
    }

    /**
     * Get customer metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        if (! $this->client) {
            return [];
        }

        return [
            'brand_id' => $this->client->brandId,
            'shipping_street_address' => $this->client->shippingStreetAddress,
            'shipping_city' => $this->client->shippingCity,
            'shipping_zip_code' => $this->client->shippingZipCode,
            'shipping_country' => $this->client->shippingCountry,
            'bank_account' => $this->client->bankAccount,
            'bank_code' => $this->client->bankCode,
            'notes' => $this->client->notes,
        ];
    }

    /**
     * Get the billable model.
     */
    public function owner(): ?BillableContract
    {
        return $this->billable;
    }

    /**
     * Get the underlying CHIP client.
     */
    public function asGatewayCustomer(): ?Client
    {
        return $this->client;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'gateway' => $this->gateway(),
            'email' => $this->email(),
            'name' => $this->name(),
            'phone' => $this->phone(),
            'address' => $this->address(),
            'metadata' => $this->metadata(),
        ];
    }
}
