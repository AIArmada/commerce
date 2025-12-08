<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Chip\Data\ClientData;

/**
 * Wrapper for CHIP customer (client).
 */
class ChipCustomer implements CustomerContract
{
    /**
     * The underlying CHIP client.
     */
    protected ?ClientData $client = null;

    /**
     * Create a new CHIP customer wrapper.
     */
    public function __construct(
        protected BillableContract $billable,
        ?ClientData $client = null
    ) {
        $this->client = $client;
    }

    /**
     * Get the customer ID.
     */
    public function id(): string
    {
        return $this->client?->id ?? $this->billable->gatewayId('chip') ?? '';
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
        return $this->client?->email ?? $this->billable->customerEmail();
    }

    /**
     * Get the customer name.
     */
    public function name(): ?string
    {
        return $this->client?->full_name ?? $this->client?->legal_name ?? $this->billable->customerName();
    }

    /**
     * Get the customer phone.
     */
    public function phone(): ?string
    {
        return $this->client?->phone ?? $this->billable->customerPhone();
    }

    /**
     * Get the customer address.
     *
     * @return array<string, mixed>|null
     */
    public function address(): ?array
    {
        if ($this->client?->street_address) {
            return [
                'line1' => $this->client->street_address,
                'line2' => null,
                'city' => $this->client->city,
                'state' => $this->client->state,
                'postal_code' => $this->client->zip_code,
                'country' => $this->client->country,
            ];
        }

        $address = $this->billable->customerAddress();

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
            'brand_name' => $this->client->brand_name,
            'shipping_street_address' => $this->client->shipping_street_address,
            'shipping_city' => $this->client->shipping_city,
            'shipping_zip_code' => $this->client->shipping_zip_code,
            'shipping_country' => $this->client->shipping_country,
            'bank_account' => $this->client->bank_account,
            'bank_code' => $this->client->bank_code,
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
    public function asGatewayCustomer(): ?ClientData
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
