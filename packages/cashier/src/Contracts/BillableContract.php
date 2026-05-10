<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Illuminate\Support\Collection;

/**
 * Contract for billable bridge models used by unified cashier features.
 *
 * The methods below describe the stable, package-owned bridge API exposed by
 * {@see \AIArmada\Cashier\Billable} and its supporting concern. Gateway
 * adapters may still call gateway-native hooks (for example Stripe- or
 * CHIP-specific customer methods), but those are grouped separately below so
 * the contract reflects the package's real responsibilities instead of an
 * idealized merged Cashier API.
 */
interface BillableContract
{
    /**
     * Get the gateway for this billable entity.
     */
    public function gateway(?string $gateway = null): GatewayContract;

    /**
    * Get the preferred gateway name for this billable.
     */
    public function preferredGateway(): string;

    /**
    * Persist the preferred gateway for this billable.
    */
    public function setPreferredGateway(string $gateway): static;

    /**
     * Get the gateway ID for a specific gateway.
     */
    public function gatewayId(?string $gateway = null): ?string;

    /**
     * Check if the billable has an ID for a specific gateway.
     */
    public function hasGatewayId(?string $gateway = null): bool;

    /**
     * Create or get the customer in the gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function createOrGetCustomer(?string $gateway = null, array $options = []): CustomerContract;

    /**
     * Update the customer in the gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateCustomer(?string $gateway = null, array $options = []): CustomerContract;

    /**
     * Sync customer details to the gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function syncCustomer(?string $gateway = null, array $options = []): CustomerContract;

    /**
     * Get the customer name.
     */
    public function customerName(): ?string;

    /**
     * Get the customer email.
     */
    public function customerEmail(): ?string;

    /**
     * Get the customer phone.
     */
    public function customerPhone(): ?string;

    /**
     * Get the customer address.
     *
     * @return array<string, mixed>
     */
    public function customerAddress(): array;

    /**
     * Get the preferred currency.
     */
    public function preferredCurrency(): string;

    /**
     * Get the preferred locale.
     */
    public function preferredLocale(): ?string;

    /**
    * Create a one-time charge using the selected gateway.
    *
    * @param  array<string, mixed>  $options
     */
    public function chargeWithGateway(int $amount, string $paymentMethod, ?string $gateway = null, array $options = []): PaymentContract;

    /**
    * Begin a new subscription on the selected gateway.
     */
    public function newGatewaySubscription(string $type, string | array $prices = [], ?string $gateway = null): SubscriptionBuilderContract;

    /**
    * Start a checkout flow on the selected gateway.
     */
    public function checkoutWithGateway(?string $gateway = null): CheckoutBuilderContract;

    /**
    * Get all subscriptions across supported gateways.
    *
    * @return Collection<int, SubscriptionContract>
     */
    public function allGatewaySubscriptions(): Collection;

    /**
    * Get subscriptions for a single gateway.
    *
    * @return Collection<int, SubscriptionContract>
     */
    public function gatewaySubscriptions(?string $gateway = null): Collection;

    /**
    * Get a single subscription for a gateway.
     */
    public function gatewaySubscription(string $type, ?string $gateway = null): ?SubscriptionContract;

    /**
    * Determine if the billable is subscribed on a specific gateway.
     */
    public function subscribedViaGateway(string $type = 'default', ?string $price = null, ?string $gateway = null): bool;

    /**
    * Get all payment methods across supported gateways.
    *
    * @return Collection<int, PaymentMethodContract>
     */
    public function allGatewayPaymentMethods(): Collection;

    /**
    * Get payment methods for a single gateway.
    *
    * @return Collection<int, PaymentMethodContract>
     */
    public function gatewayPaymentMethods(?string $gateway = null, ?string $type = null): Collection;

    /**
    * Get the default payment method for a gateway.
     */
    public function defaultGatewayPaymentMethod(?string $gateway = null): ?PaymentMethodContract;

    /**
    * Create a setup intent (or gateway equivalent) for the selected gateway.
    *
    * @param  array<string, mixed>  $options
     */
    public function createGatewaySetupIntent(?string $gateway = null, array $options = []): mixed;

    /**
    * Get all invoices across supported gateways.
    *
    * @param  array<string, mixed>  $parameters
    * @return Collection<int, InvoiceContract>
     */
    public function allGatewayInvoices(array $parameters = []): Collection;

    /**
    * Get invoices for a single gateway.
    *
    * @param  array<string, mixed>  $parameters
    * @return Collection<int, InvoiceContract>
     */
    public function gatewayInvoices(?string $gateway = null, array $parameters = []): Collection;

    /**
    * Get the customer billing portal URL for a gateway when supported.
    *
    * @param  array<string, mixed>  $options
     */
    public function gatewayBillingPortalUrl(string $returnUrl, ?string $gateway = null, array $options = []): ?string;

    /**
    * Get all subscriptions across all available gateways.
    *
    * @return Collection<int, SubscriptionContract>
     */
    public function allSubscriptions(): Collection;

    /**
    * Find the first matching subscription across all available gateways.
     */
    public function findSubscription(string $type = 'default'): ?SubscriptionContract;

    /**
    * Determine if the billable is subscribed on any gateway.
     */
    public function subscribedOnAny(string $type = 'default', ?string $price = null): bool;

    /**
    * Determine if the billable is on trial on any gateway.
     */
    public function onTrialOnAny(string $type = 'default'): bool;

    /**
    * Determine if the billable is on a generic trial stored on the model.
     */
    public function onGenericTrial(): bool;

    /**
    * Gateway-native bridge hooks required by the Stripe adapter.
    */

    /**
     * Stripe: Get the Stripe customer ID.
     */
    public function stripeId(): ?string;

    /**
     * Stripe: Get the Stripe customer.
     */
    public function asStripeCustomer(array $expand = []): mixed;

    /**
     * Stripe: Create or get the Stripe customer.
     *
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $requestOptions
     */
    public function createOrGetStripeCustomer(array $options = [], array $requestOptions = []): mixed;

    /**
     * Stripe: Update the Stripe customer.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateStripeCustomer(array $options = []): mixed;

    /**
     * Stripe: Sync the Stripe customer details.
     *
     * @param  array<string, mixed>  $options
     */
    public function syncStripeCustomerDetails(array $options = []): mixed;

    /**
     * Stripe: Create a setup intent.
     *
     * @param  array<string, mixed>  $options
     */
    public function createSetupIntent(array $options = []): mixed;

    /**
     * Stripe: Get the Stripe billing portal URL.
     *
     * @param  array<string, mixed>  $options
     */
    public function billingPortalUrl(?string $returnUrl = null, array $options = []): string;

    /**
     * Gateway-native bridge hooks required by the CHIP adapter.
     */

    /**
     * CHIP: Create or get the CHIP customer.
     *
     * @param  array<string, mixed>  $options
     */
    public function createOrGetChipCustomer(array $options = []): mixed;

    /**
     * CHIP: Update the CHIP customer.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateChipCustomer(array $options = []): mixed;

    /**
     * CHIP: Sync the CHIP customer details.
     */
    public function syncChipCustomerDetails(): mixed;

    /**
     * CHIP: Create a setup purchase.
     *
     * @param  array<string, mixed>  $options
     */
    public function createSetupPurchase(array $options = []): mixed;
}
