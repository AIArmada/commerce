<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutBuilderContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Exceptions\GatewayRetrievalException;
use AIArmada\Cashier\Exceptions\Webhook\WebhookVerificationException;
use AIArmada\Cashier\Gateways\Stripe\StripeCheckoutBuilder;
use AIArmada\Cashier\Gateways\Stripe\StripeCustomer;
use AIArmada\Cashier\Gateways\Stripe\StripeInvoice;
use AIArmada\Cashier\Gateways\Stripe\StripePayment;
use AIArmada\Cashier\Gateways\Stripe\StripePaymentMethod;
use AIArmada\Cashier\Gateways\Stripe\StripeSubscription;
use AIArmada\Cashier\Gateways\Stripe\StripeSubscriptionBuilder;
use AIArmada\Cashier\Support\PaymentOperationLimiter;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Laravel\Cashier\Invoice as CashierInvoice;
use Laravel\Cashier\Payment;
use SensitiveParameter;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\EventService;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;

/**
 * Stripe payment gateway implementation.
 *
 * This gateway wraps Laravel Cashier for Stripe functionality,
 * providing a unified interface compatible with the multi-gateway system.
 */
class StripeGateway extends AbstractGateway
{
    /**
     * Get the gateway name.
     */
    public function name(): string
    {
        return 'stripe';
    }

    /**
     * Get the Stripe client.
     */
    public function client(): StripeClient
    {
        return Cashier::stripe();
    }

    /**
     * Get the customer adapter for this gateway.
     */
    public function customer(BillableContract $billable): CustomerContract
    {
        $stripeCustomer = $this->callBillableMethod($billable, 'asStripeCustomer');

        return new StripeCustomer($stripeCustomer, $billable);
    }

    /**
     * Create or get a customer on Stripe.
     *
     * @param  array<string, mixed>  $options
     */
    public function createCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        $stripeCustomer = $this->callBillableMethod($billable, 'createOrGetStripeCustomer', [$options]);

        return new StripeCustomer($stripeCustomer, $billable);
    }

    /**
     * Update customer on Stripe.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        $stripeCustomer = $this->callBillableMethod($billable, 'updateStripeCustomer', [$options]);

        return new StripeCustomer($stripeCustomer, $billable);
    }

    /**
     * Sync customer information to Stripe.
     *
     * @param  array<string, mixed>  $options
     */
    public function syncCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        $stripeCustomer = $this->callBillableMethod($billable, 'syncStripeCustomerDetails', [$options]);

        return new StripeCustomer($stripeCustomer, $billable);
    }

    /**
     * Create a one-time charge.
     *
     * @param  array<string, mixed>  $options
     */
    public function charge(BillableContract $billable, int $amount, #[SensitiveParameter] ?string $paymentMethod = null, array $options = []): PaymentContract
    {
        $chargeOptions = Arr::except($options, [
            'success_url',
            'failure_url',
            'cancel_url',
            'redirect_urls',
            'product_name',
            'reference',
        ]);

        $payment = PaymentOperationLimiter::run(
            $this->name(),
            'charge',
            $billable,
            fn (): mixed => $paymentMethod !== null
                ? $this->callBillableMethod($billable, 'charge', [$amount, $paymentMethod, $chargeOptions])
                : $this->callBillableMethod($billable, 'pay', [$amount, $chargeOptions]),
        );

        return new StripePayment($payment);
    }

    /**
     * Refund a payment.
     *
     * @param  int|null  $amount  Amount to refund in cents (null for full refund)
     */
    public function refund(string $paymentId, ?int $amount = null): mixed
    {
        if ($amount !== null && $amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be a positive integer in minor units.');
        }

        return PaymentOperationLimiter::run(
            $this->name(),
            'refund',
            'payment:' . $paymentId,
            function () use ($paymentId, $amount): StripePayment {
                $parameters = ['payment_intent' => $paymentId];

                if ($amount !== null) {
                    $parameters['amount'] = $amount;
                }

                $this->client()->refunds->create($parameters);

                $payment = $this->client()->paymentIntents->retrieve($paymentId);

                return new StripePayment(new Payment($payment));
            },
        );
    }

    /**
     * Create a new subscription builder.
     */
    public function subscription(BillableContract $billable, string $type, string | array $prices = []): SubscriptionBuilderContract
    {
        return new StripeSubscriptionBuilder($billable, $type, $prices);
    }

    /**
     * Create a new checkout session builder.
     */
    public function checkout(BillableContract $billable): CheckoutBuilderContract
    {
        return new StripeCheckoutBuilder($this, $billable);
    }

    /**
     * Retrieve a checkout session.
     */
    public function retrieveCheckout(string $sessionId): ?CheckoutContract
    {
        try {
            $session = $this->client()->checkout->sessions->retrieve($sessionId);

            if (! $this->stripeCustomerBelongsToCurrentOwner($session->customer ?? null)) {
                $this->logCrossOwnerBlocked('checkout session', $sessionId);

                return null;
            }

            return new Stripe\StripeCheckout($session);
        } catch (InvalidRequestException $e) {
            if ($e->getHttpStatus() === 404) {
                return null;
            }

            throw GatewayRetrievalException::create('stripe', 'checkout session', $sessionId, $e);
        } catch (Throwable $e) {
            throw GatewayRetrievalException::create('stripe', 'checkout session', $sessionId, $e);
        }
    }

    /**
     * Retrieve a subscription.
     */
    public function retrieveSubscription(string $subscriptionId): ?SubscriptionContract
    {
        try {
            $subscription = $this->client()->subscriptions->retrieve($subscriptionId);

            if (! $this->stripeCustomerBelongsToCurrentOwner($subscription->customer ?? null)) {
                $this->logCrossOwnerBlocked('subscription', $subscriptionId);

                return null;
            }

            return new StripeSubscription($subscription);
        } catch (InvalidRequestException $e) {
            if ($e->getHttpStatus() === 404) {
                return null;
            }

            throw GatewayRetrievalException::create('stripe', 'subscription', $subscriptionId, $e);
        } catch (Throwable $e) {
            throw GatewayRetrievalException::create('stripe', 'subscription', $subscriptionId, $e);
        }
    }

    /**
     * Retrieve a payment.
     */
    public function retrievePayment(string $paymentId): ?PaymentContract
    {
        try {
            $paymentIntent = $this->client()->paymentIntents->retrieve($paymentId);

            if (! $this->stripeCustomerBelongsToCurrentOwner($paymentIntent->customer ?? null)) {
                $this->logCrossOwnerBlocked('payment', $paymentId);

                return null;
            }

            return new StripePayment(new Payment($paymentIntent));
        } catch (InvalidRequestException $e) {
            if ($e->getHttpStatus() === 404) {
                return null;
            }

            throw GatewayRetrievalException::create('stripe', 'payment', $paymentId, $e);
        } catch (Throwable $e) {
            throw GatewayRetrievalException::create('stripe', 'payment', $paymentId, $e);
        }
    }

    /**
     * Retrieve an invoice.
     */
    public function retrieveInvoice(string $invoiceId): ?InvoiceContract
    {
        try {
            $invoice = $this->client()->invoices->retrieve($invoiceId);

            if (! $this->stripeCustomerBelongsToCurrentOwner($invoice->customer ?? null)) {
                $this->logCrossOwnerBlocked('invoice', $invoiceId);

                return null;
            }

            return new StripeInvoice($invoice);
        } catch (InvalidRequestException $e) {
            if ($e->getHttpStatus() === 404) {
                return null;
            }

            throw GatewayRetrievalException::create('stripe', 'invoice', $invoiceId, $e);
        } catch (Throwable $e) {
            throw GatewayRetrievalException::create('stripe', 'invoice', $invoiceId, $e);
        }
    }

    /**
     * Record an ownership denial so it is distinguishable from a 404.
     *
     * The null return contract is preserved; the distinction lives in the
     * security log, not the return value.
     */
    private function logCrossOwnerBlocked(string $resource, string $identifier): void
    {
        Log::warning('Cashier blocked a cross-owner gateway retrieval.', [
            'gateway' => $this->name(),
            'resource' => $resource,
            'identifier' => $identifier,
        ]);
    }

    private function stripeCustomerBelongsToCurrentOwner(mixed $customer): bool
    {
        $customerId = is_string($customer) ? $customer : data_get($customer, 'id');

        if (! is_string($customerId) || mb_trim($customerId) === '') {
            return false;
        }

        return $this->resolveBillableByGatewayId(mb_trim($customerId)) instanceof BillableContract;
    }

    /**
     * Get all subscriptions for a customer.
     *
     * @return Collection<int, SubscriptionContract>
     */
    public function subscriptions(BillableContract $billable): Collection
    {
        $subscriptionsRelation = $this->callBillableMethod($billable, 'subscriptions');

        if ($subscriptionsRelation instanceof Relation) {
            $subscriptionsRelation = $subscriptionsRelation
                ->with('items')
                ->limit(self::DEFAULT_LIST_LIMIT);
        }

        $subscriptions = $subscriptionsRelation->get()
            ->map(fn ($subscription) => new StripeSubscription($subscription))
            ->values();

        /** @var Collection<int, SubscriptionContract> $subscriptions */
        return $subscriptions;
    }

    /**
     * Get all invoices for a customer.
     *
     * @param  bool|array<string, mixed>  $parameters  Either includePending bool or parameters array
     * @return Collection<int, InvoiceContract>
     */
    public function invoices(BillableContract $billable, bool | array $parameters = false): Collection
    {
        $includePending = is_bool($parameters) ? $parameters : (bool) ($parameters['include_pending'] ?? false);
        $invoiceParameters = is_array($parameters) ? $parameters : [];

        $rawInvoices = $this->callBillableMethod($billable, 'invoices', [$includePending, $invoiceParameters]);

        if (! is_iterable($rawInvoices)) {
            return collect();
        }

        /** @var iterable<int, mixed> $rawInvoices */
        $invoices = collect($rawInvoices)
            ->map(fn ($invoice) => new StripeInvoice($invoice instanceof CashierInvoice ? $invoice : $invoice->asStripeInvoice()))
            ->values();

        /** @var Collection<int, InvoiceContract> $invoices */
        return $invoices;
    }

    /**
     * Get all payment methods for a customer.
     *
     * @param  string|null  $type  Filter by payment method type (e.g., 'card')
     * @return Collection<int, PaymentMethodContract>
     */
    public function paymentMethods(BillableContract $billable, ?string $type = null): Collection
    {
        $arguments = $type === null ? [] : [$type];

        $rawPaymentMethods = $this->callBillableMethod($billable, 'paymentMethods', $arguments);

        if (! is_iterable($rawPaymentMethods)) {
            return collect();
        }

        /** @var iterable<int, mixed> $rawPaymentMethods */
        $paymentMethods = collect($rawPaymentMethods)
            ->map(fn ($paymentMethod) => new StripePaymentMethod($paymentMethod, $billable))
            ->values();

        /** @var Collection<int, PaymentMethodContract> $paymentMethods */
        return $paymentMethods;
    }

    /**
     * Find a specific payment method.
     */
    public function findPaymentMethod(BillableContract $billable, string $paymentMethodId): ?PaymentMethodContract
    {
        $paymentMethod = $this->callBillableMethod($billable, 'findPaymentMethod', [$paymentMethodId]);

        if (! $paymentMethod) {
            return null;
        }

        return new StripePaymentMethod($paymentMethod, $billable);
    }

    /**
     * Get the default payment method for a customer.
     */
    public function defaultPaymentMethod(BillableContract $billable): ?PaymentMethodContract
    {
        $paymentMethod = $this->callBillableMethod($billable, 'defaultPaymentMethod');

        if (! $paymentMethod) {
            return null;
        }

        return new StripePaymentMethod($paymentMethod, $billable);
    }

    /**
     * Create a setup intent for adding payment methods.
     *
     * @param  array<string, mixed>  $options
     */
    public function createSetupIntent(BillableContract $billable, array $options = []): mixed
    {
        return $this->callBillableMethod($billable, 'createSetupIntent', [$options]);
    }

    /**
     * Verify a webhook signature.
     *
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhookSignature(string $payload, array $headers): bool
    {
        $signature = $headers['Stripe-Signature'] ?? $headers['stripe-signature'] ?? '';
        $secret = $this->webhookSecret();

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        try {
            Webhook::constructEvent($payload, $signature, $secret);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Handle a webhook event.
     *
     * When the raw request body is supplied, the signature is verified
     * against it before the Cashier webhook controller runs.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function handleWebhook(array $payload, array $headers = [], ?string $rawPayload = null): mixed
    {
        if ($rawPayload !== null && ! $this->verifyWebhookSignature($rawPayload, $headers)) {
            throw WebhookVerificationException::invalidSignature($this->name());
        }

        $signature = $headers['Stripe-Signature'] ?? $headers['stripe-signature'] ?? null;
        $server = is_string($signature) && $signature !== ''
            ? ['HTTP_STRIPE_SIGNATURE' => $signature]
            : [];

        $body = $rawPayload ?? json_encode($payload, JSON_THROW_ON_ERROR);

        $request = Request::create('/', 'POST', [], [], [], $server, $body);

        return app(WebhookController::class)->handleWebhook($request);
    }

    /**
     * Re-fetch a webhook event from the Stripe API for trusted replay.
     *
     * @return array<string, mixed>
     */
    public function fetchWebhookEvent(string $eventId): array
    {
        try {
            $event = (new EventService($this->client()))->retrieve($eventId);
        } catch (InvalidRequestException $e) {
            throw GatewayRetrievalException::create('stripe', 'webhook event', $eventId, $e);
        }

        /** @var array<string, mixed> $payload */
        $payload = $event->toArray();

        return $payload;
    }

    /**
     * Get the customer portal URL.
     *
     * @param  array<string, mixed>  $options
     */
    public function customerPortalUrl(BillableContract $billable, string $returnUrl, array $options = []): string
    {
        return $this->callBillableMethod($billable, 'billingPortalUrl', [$returnUrl, $options]);
    }

    /**
     * Create a billing portal session.
     *
     * @param  array<string, mixed>  $options
     */
    public function createBillingPortalSession(BillableContract $billable, string $returnUrl, array $options = []): mixed
    {
        return $this->client()->billingPortal->sessions->create(array_merge([
            'customer' => $this->callBillableMethod($billable, 'stripeId'),
            'return_url' => $returnUrl,
        ], $options));
    }

    /**
     * List all plans/prices.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function prices(array $parameters = []): Collection
    {
        $prices = $this->client()->prices->all($parameters);

        $data = $prices->data;

        return collect(is_array($data) ? $data : []);
    }

    /**
     * List all products.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function products(array $parameters = []): Collection
    {
        $products = $this->client()->products->all($parameters);

        $data = $products->data;

        return collect(is_array($data) ? $data : []);
    }

    /**
     * Get a specific price.
     */
    public function price(string $priceId): mixed
    {
        return $this->client()->prices->retrieve($priceId);
    }

    /**
     * Get a specific product.
     */
    public function product(string $productId): mixed
    {
        return $this->client()->products->retrieve($productId);
    }
}
