<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Concerns;

use AIArmada\Cashier\Contracts\CheckoutBuilderContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Facades\Cashier;
use Illuminate\Support\Collection;

/**
 * Unified Billable trait for multi-gateway support.
 *
 * This trait provides a unified interface for interacting with multiple
 * payment gateways (Stripe, CHIP, etc.) through a single API.
 */
trait ManagesGateway // @phpstan-ignore trait.unused
{
    /**
     * Get a gateway instance.
     */
    public function gateway(?string $name = null): GatewayContract
    {
        return Cashier::gateway($name ?? $this->preferredGateway());
    }

    /**
     * Get the preferred gateway for this billable.
     */
    public function preferredGateway(): string
    {
        return $this->preferred_gateway ?? config('cashier.default', 'stripe');
    }

    /**
     * Set the preferred gateway.
     */
    public function setPreferredGateway(string $gateway): static
    {
        $this->preferred_gateway = $gateway;
        $this->save();

        return $this;
    }

    /**
     * Get the gateway ID for a specific gateway.
     *
     * The column name follows the convention: {gateway}_id
     * Each gateway package provides its own migration to add this column.
     * For example: stripe_id (from laravel/cashier), chip_id (from cashier-chip)
     */
    public function gatewayId(?string $gateway = null): ?string
    {
        $gateway = $gateway ?? $this->preferredGateway();
        $column = $gateway . '_id';

        return $this->{$column} ?? null;
    }

    /**
     * Determine if the billable has a customer ID on the given gateway.
     */
    public function hasGatewayId(?string $gateway = null): bool
    {
        return ! is_null($this->gatewayId($gateway));
    }

    /**
     * Create or get a customer on the preferred gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function createOrGetCustomer(?string $gateway = null, array $options = []): CustomerContract
    {
        return $this->gateway($gateway)->createCustomer($this, $options);
    }

    /**
     * Update customer on the preferred gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateCustomer(?string $gateway = null, array $options = []): CustomerContract
    {
        return $this->gateway($gateway)->updateCustomer($this, $options);
    }

    /**
     * Sync customer information to the gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function syncCustomer(?string $gateway = null, array $options = []): CustomerContract
    {
        return $this->gateway($gateway)->syncCustomer($this, $options);
    }

    /**
     * Create a one-time charge.
     *
     * @param  array<string, mixed>  $options
     */
    public function chargeWithGateway(int $amount, string $paymentMethod, ?string $gateway = null, array $options = []): PaymentContract
    {
        return $this->gateway($gateway)->charge($this, $amount, $paymentMethod, $options);
    }

    /**
     * Create a new subscription builder for the given type.
     *
     * @param  string|array<string>  $prices
     */
    public function newGatewaySubscription(string $type, string | array $prices = [], ?string $gateway = null): SubscriptionBuilderContract
    {
        return $this->gateway($gateway)->newSubscription($this, $type, $prices);
    }

    /**
     * Create a checkout session.
     */
    public function checkoutWithGateway(?string $gateway = null): CheckoutBuilderContract
    {
        return $this->gateway($gateway)->checkout($this);
    }

    /**
     * Get all subscriptions across all gateways.
     *
     * @return Collection<int, SubscriptionContract>
     */
    public function allGatewaySubscriptions(): Collection
    {
        $subscriptions = collect();

        foreach (Cashier::supportedGateways() as $gateway) {
            $subscriptions = $subscriptions->merge(
                $this->gateway($gateway)->subscriptions($this)
            );
        }

        return $subscriptions;
    }

    /**
     * Get subscriptions for a specific gateway.
     *
     * @return Collection<int, SubscriptionContract>
     */
    public function gatewaySubscriptions(?string $gateway = null): Collection
    {
        return $this->gateway($gateway)->subscriptions($this);
    }

    /**
     * Get a specific subscription.
     */
    public function gatewaySubscription(string $type, ?string $gateway = null): ?SubscriptionContract
    {
        return $this->gatewaySubscriptions($gateway)
            ->first(fn (SubscriptionContract $sub) => $sub->type() === $type);
    }

    /**
     * Determine if subscribed to any plan.
     */
    public function subscribedViaGateway(string $type = 'default', ?string $price = null, ?string $gateway = null): bool
    {
        $subscription = $this->gatewaySubscription($type, $gateway);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return $price ? $subscription->hasPrice($price) : true;
    }

    /**
     * Get all payment methods across all gateways.
     *
     * @return Collection<int, PaymentMethodContract>
     */
    public function allGatewayPaymentMethods(): Collection
    {
        $methods = collect();

        foreach (Cashier::supportedGateways() as $gateway) {
            $methods = $methods->merge(
                $this->gateway($gateway)->paymentMethods($this)
            );
        }

        return $methods;
    }

    /**
     * Get payment methods for a specific gateway.
     *
     * @return Collection<int, PaymentMethodContract>
     */
    public function gatewayPaymentMethods(?string $gateway = null, ?string $type = null): Collection
    {
        return $this->gateway($gateway)->paymentMethods($this, $type);
    }

    /**
     * Get the default payment method for a gateway.
     */
    public function defaultGatewayPaymentMethod(?string $gateway = null): ?PaymentMethodContract
    {
        return $this->gateway($gateway)->defaultPaymentMethod($this);
    }

    /**
     * Create a setup intent for adding payment methods.
     *
     * @param  array<string, mixed>  $options
     */
    public function createGatewaySetupIntent(?string $gateway = null, array $options = []): mixed
    {
        return $this->gateway($gateway)->createSetupIntent($this, $options);
    }

    /**
     * Get all invoices across all gateways.
     *
     * @param  array<string, mixed>  $parameters
     * @return Collection<int, InvoiceContract>
     */
    public function allGatewayInvoices(array $parameters = []): Collection
    {
        $invoices = collect();

        foreach (Cashier::supportedGateways() as $gateway) {
            $invoices = $invoices->merge(
                $this->gateway($gateway)->invoices($this, $parameters)
            );
        }

        return $invoices;
    }

    /**
     * Get invoices for a specific gateway.
     *
     * @param  array<string, mixed>  $parameters
     * @return Collection<int, InvoiceContract>
     */
    public function gatewayInvoices(?string $gateway = null, array $parameters = []): Collection
    {
        return $this->gateway($gateway)->invoices($this, $parameters);
    }

    /**
     * Get the customer portal URL for a gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function gatewayBillingPortalUrl(string $returnUrl, ?string $gateway = null, array $options = []): ?string
    {
        $gatewayInstance = $this->gateway($gateway);

        if (method_exists($gatewayInstance, 'customerPortalUrl')) {
            return $gatewayInstance->customerPortalUrl($this, $returnUrl, $options);
        }

        return null;
    }
}
