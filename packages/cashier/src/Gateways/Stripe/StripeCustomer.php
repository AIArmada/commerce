<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Stripe;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use Stripe\Customer;

/**
 * Wrapper for Stripe customer.
 */
class StripeCustomer implements CustomerContract
{
    /**
     * Create a new Stripe customer wrapper.
     */
    public function __construct(
        protected Customer $customer,
        protected ?BillableContract $billable = null
    ) {}

    /**
     * Get the customer ID.
     */
    public function id(): string
    {
        return $this->customer->id;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'stripe';
    }

    /**
     * Get the customer email.
     */
    public function email(): ?string
    {
        return $this->customer->email;
    }

    /**
     * Get the customer name.
     */
    public function name(): ?string
    {
        return $this->customer->name;
    }

    /**
     * Get the customer phone.
     */
    public function phone(): ?string
    {
        return $this->customer->phone;
    }

    /**
     * Get the customer address.
     *
     * @return array<string, mixed>|null
     */
    public function address(): ?array
    {
        if (! $this->customer->address) {
            return null;
        }

        return [
            'line1' => $this->customer->address->line1,
            'line2' => $this->customer->address->line2,
            'city' => $this->customer->address->city,
            'state' => $this->customer->address->state,
            'postal_code' => $this->customer->address->postal_code,
            'country' => $this->customer->address->country,
        ];
    }

    /**
     * Get customer metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->customer->metadata?->toArray() ?? [];
    }

    /**
     * Get the billable model.
     */
    public function owner(): ?BillableContract
    {
        return $this->billable;
    }

    /**
     * Get the underlying Stripe customer.
     */
    public function asGatewayCustomer(): Customer
    {
        return $this->customer;
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
