<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Stripe;

use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use Illuminate\Http\RedirectResponse;
use Stripe\Checkout\Session;

/**
 * Wrapper for Stripe checkout session.
 */
class StripeCheckout implements CheckoutContract
{
    /**
     * Create a new Stripe checkout wrapper.
     */
    public function __construct(
        protected Session $session
    ) {}

    /**
     * Get the checkout session ID.
     */
    public function id(): string
    {
        return $this->session->id;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'stripe';
    }

    /**
     * Get the checkout URL.
     */
    public function url(): string
    {
        return $this->session->url;
    }

    /**
     * Redirect to the checkout page.
     */
    public function redirect(): RedirectResponse
    {
        return redirect()->to($this->url());
    }

    /**
     * Get the success URL.
     */
    public function successUrl(): string
    {
        return $this->session->success_url;
    }

    /**
     * Get the cancel URL.
     */
    public function cancelUrl(): string
    {
        return $this->session->cancel_url;
    }

    /**
     * Get the checkout status.
     */
    public function status(): string
    {
        return $this->session->status ?? 'open';
    }

    /**
     * Get the payment status.
     */
    public function paymentStatus(): string
    {
        return $this->session->payment_status;
    }

    /**
     * Determine if the checkout is complete.
     */
    public function isComplete(): bool
    {
        return $this->status() === 'complete';
    }

    /**
     * Determine if the checkout was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->paymentStatus() === 'paid';
    }

    /**
     * Determine if the checkout is pending.
     */
    public function isPending(): bool
    {
        return $this->paymentStatus() === 'unpaid' && $this->status() === 'open';
    }

    /**
     * Determine if the checkout has expired.
     */
    public function isExpired(): bool
    {
        return $this->status() === 'expired';
    }

    /**
     * Get the total amount in cents.
     */
    public function rawTotal(): int
    {
        return $this->session->amount_total ?? 0;
    }

    /**
     * Get the formatted total.
     */
    public function total(): string
    {
        return number_format($this->rawTotal() / 100, 2) . ' ' . mb_strtoupper($this->currency());
    }

    /**
     * Get the currency.
     */
    public function currency(): string
    {
        return mb_strtoupper($this->session->currency ?? 'USD');
    }

    /**
     * Get the customer if available.
     */
    public function customer(): ?CustomerContract
    {
        // Would need to resolve from Stripe
        return null;
    }

    /**
     * Get the underlying gateway checkout object.
     */
    public function asGatewayCheckout(): Session
    {
        return $this->session;
    }
}
