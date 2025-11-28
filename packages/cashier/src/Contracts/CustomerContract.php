<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

/**
 * Contract for customer representations across gateways.
 */
interface CustomerContract
{
    /**
     * Get the customer ID in the gateway.
     */
    public function id(): string;

    /**
     * Get the customer email.
     */
    public function email(): ?string;

    /**
     * Get the customer name.
     */
    public function name(): ?string;

    /**
     * Get the customer phone.
     */
    public function phone(): ?string;

    /**
     * Get the customer address.
     *
     * @return array<string, mixed>|null
     */
    public function address(): ?array;

    /**
     * Get the gateway name.
     */
    public function gateway(): string;

    /**
     * Get the raw customer data from the gateway.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Get the underlying gateway customer object.
     */
    public function asGatewayCustomer(): mixed;
}
