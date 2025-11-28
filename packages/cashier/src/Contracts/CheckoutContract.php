<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Illuminate\Http\RedirectResponse;

/**
 * Contract for checkout sessions.
 */
interface CheckoutContract
{
    /**
     * Get the checkout session ID.
     */
    public function id(): string;

    /**
     * Get the gateway name.
     */
    public function gateway(): string;

    /**
     * Get the checkout URL.
     */
    public function url(): string;

    /**
     * Redirect to the checkout page.
     */
    public function redirect(): RedirectResponse;

    /**
     * Get the success URL.
     */
    public function successUrl(): string;

    /**
     * Get the cancel URL.
     */
    public function cancelUrl(): string;

    /**
     * Get the checkout status.
     */
    public function status(): string;

    /**
     * Get the payment status.
     */
    public function paymentStatus(): string;

    /**
     * Determine if the checkout is complete.
     */
    public function isComplete(): bool;

    /**
     * Determine if the checkout was successful.
     */
    public function isSuccessful(): bool;

    /**
     * Determine if the checkout is pending.
     */
    public function isPending(): bool;

    /**
     * Determine if the checkout has expired.
     */
    public function isExpired(): bool;

    /**
     * Get the total amount in cents.
     */
    public function rawTotal(): int;

    /**
     * Get the formatted total.
     */
    public function total(): string;

    /**
     * Get the currency.
     */
    public function currency(): string;

    /**
     * Get the customer if available.
     */
    public function customer(): ?CustomerContract;

    /**
     * Get the underlying gateway checkout object.
     */
    public function asGatewayCheckout(): mixed;
}
