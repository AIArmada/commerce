<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Illuminate\Support\Collection;

/**
 * Contract for payment gateway implementations.
 *
 * Each payment gateway (Stripe, CHIP, Paddle, etc.) must implement this contract
 * to be used with the unified Cashier system.
 */
interface GatewayContract
{
    /**
     * Get the gateway name/identifier.
     */
    public function name(): string;

    /**
     * Get the gateway display name.
     */
    public function displayName(): string;

    /**
     * Check if the gateway is available (dependencies installed, configured).
     */
    public function isAvailable(): bool;

    /**
     * Get the default currency for this gateway.
     */
    public function currency(): string;

    /**
     * Get the currency locale for formatting.
     */
    public function currencyLocale(): string;

    /**
     * Format an amount for display.
     *
     * @param  int  $amount  Amount in cents/smallest currency unit
     * @param  string|null  $currency  Currency code (defaults to gateway currency)
     */
    public function formatAmount(int $amount, ?string $currency = null): string;

    /**
     * Get the customer adapter for this gateway.
     */
    public function customer(BillableContract $billable): CustomerContract;

    /**
     * Create or get a customer on the gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function createCustomer(BillableContract $billable, array $options = []): CustomerContract;

    /**
     * Update customer on the gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateCustomer(BillableContract $billable, array $options = []): CustomerContract;

    /**
     * Sync customer information to the gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function syncCustomer(BillableContract $billable, array $options = []): CustomerContract;

    /**
     * Create a new subscription builder for this gateway.
     *
     * @param  string|array<string>  $prices
     */
    public function newSubscription(BillableContract $billable, string $type, string | array $prices = []): SubscriptionBuilderContract;

    /**
     * Create a new subscription builder (alias for newSubscription).
     *
     * @param  string|array<string>  $prices
     */
    public function subscription(BillableContract $billable, string $type, string | array $prices = []): SubscriptionBuilderContract;

    /**
     * Get all subscriptions for a billable entity.
     *
     * @return Collection<int, SubscriptionContract>
     */
    public function subscriptions(BillableContract $billable): Collection;

    /**
     * Get payment methods for a billable entity.
     *
     * @param  string|null  $type  Filter by payment method type (e.g., 'card')
     * @return Collection<int, PaymentMethodContract>
     */
    public function paymentMethods(BillableContract $billable, ?string $type = null): Collection;

    /**
     * Find a specific payment method.
     */
    public function findPaymentMethod(BillableContract $billable, string $paymentMethodId): ?PaymentMethodContract;

    /**
     * Get the default payment method for a billable entity.
     */
    public function defaultPaymentMethod(BillableContract $billable): ?PaymentMethodContract;

    /**
     * Create a setup intent for adding payment methods.
     *
     * @param  array<string, mixed>  $options
     */
    public function createSetupIntent(BillableContract $billable, array $options = []): mixed;

    /**
     * Create a charge/payment.
     *
     * @param  int  $amount  Amount in cents
     * @param  array<string, mixed>  $options
     */
    public function charge(BillableContract $billable, int $amount, ?string $paymentMethod = null, array $options = []): PaymentContract;

    /**
     * Create a checkout session builder.
     */
    public function checkout(BillableContract $billable): CheckoutBuilderContract;

    /**
     * Retrieve a checkout session by ID.
     */
    public function retrieveCheckout(string $sessionId): ?CheckoutContract;

    /**
     * Find a payment/purchase by ID.
     */
    public function findPayment(string $paymentId): ?PaymentContract;

    /**
     * Refund a payment.
     *
     * @param  int|null  $amount  Amount to refund in cents (null for full refund)
     */
    public function refund(string $paymentId, ?int $amount = null): mixed;

    /**
     * Get invoices for a billable entity.
     *
     * @param  bool|array<string, mixed>  $parameters  Either includePending bool or parameters array
     * @return Collection<int, InvoiceContract>
     */
    public function invoices(BillableContract $billable, bool | array $parameters = false): Collection;

    /**
     * Find a specific invoice.
     */
    public function findInvoice(BillableContract $billable, string $invoiceId): ?InvoiceContract;

    /**
     * Handle an incoming webhook payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function handleWebhook(array $payload, array $headers = []): mixed;

    /**
     * Verify a webhook signature.
     *
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhookSignature(string $payload, array $headers): bool;

    /**
     * Get the webhook secret for this gateway.
     */
    public function webhookSecret(): ?string;

    /**
     * Get the customer billing portal URL.
     *
     * @param  array<string, mixed>  $options
     */
    public function customerPortalUrl(BillableContract $billable, string $returnUrl, array $options = []): string;

    /**
     * Get the underlying gateway client/service.
     */
    public function client(): mixed;
}
