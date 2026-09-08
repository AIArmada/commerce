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
use AIArmada\Cashier\Gateways\Chip\ChipCheckoutBuilder;
use AIArmada\Cashier\Gateways\Chip\ChipCustomer;
use AIArmada\Cashier\Gateways\Chip\ChipPayment;
use AIArmada\Cashier\Gateways\Chip\ChipPaymentMethod;
use AIArmada\Cashier\Gateways\Chip\ChipSubscription;
use AIArmada\Cashier\Gateways\Chip\ChipSubscriptionBuilder;
use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\CashierChip\Billing\Cashier as CashierChip;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\Chip\Actions\DispatchChipWebhookAction;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipApiException;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use SensitiveParameter;
use Throwable;

/**
 * CHIP payment gateway implementation.
 *
 * This gateway wraps the cashier-chip package for CHIP functionality,
 * providing a unified interface compatible with the multi-gateway system.
 */
class ChipGateway extends AbstractGateway
{
    /**
     * Get the gateway name.
     */
    public function name(): string
    {
        return 'chip';
    }

    /**
     * Get the CHIP client/service.
     */
    public function client(): ChipCollectService
    {
        return CashierChip::chip();
    }

    /**
     * Get the customer adapter for this gateway.
     */
    public function customer(BillableContract $billable): CustomerContract
    {
        return new ChipCustomer($billable);
    }

    protected function resolveBillableByGatewayId(string $gatewayId): ?BillableContract
    {
        $billable = CashierChip::findBillable($gatewayId);

        if (! $billable instanceof BillableContract) {
            return null;
        }

        return $billable;
    }

    /**
     * Create or get a customer on CHIP.
     *
     * @param  array<string, mixed>  $options
     */
    public function createCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        $chipCustomer = $this->callBillableMethod($billable, 'createOrGetChipCustomer', [$options]);

        return new ChipCustomer($billable, $chipCustomer);
    }

    /**
     * Update customer on CHIP.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        $chipCustomer = $this->callBillableMethod($billable, 'updateChipCustomer', [$options]);

        return new ChipCustomer($billable, $chipCustomer);
    }

    /**
     * Sync customer information to CHIP.
     *
     * @param  array<string, mixed>  $options
     */
    public function syncCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        $chipCustomer = $this->callBillableMethod($billable, 'syncChipCustomerDetails');

        return new ChipCustomer($billable, $chipCustomer);
    }

    /**
     * Create a one-time charge.
     *
     * @param  array<string, mixed>  $options
     */
    public function charge(BillableContract $billable, int $amount, #[SensitiveParameter] ?string $paymentMethod = null, array $options = []): PaymentContract
    {
        $payment = $this->callBillableMethod($billable, 'charge', [$amount, $paymentMethod, $options]);

        return new ChipPayment($payment);
    }

    /**
     * Refund a payment.
     *
     * @param  int|null  $amount  Amount to refund in cents (null for full refund)
     */
    public function refund(string $paymentId, ?int $amount = null): mixed
    {
        $purchase = $this->client()->refundPurchase($paymentId, $amount);

        if ($purchase instanceof PaymentData) {
            return new ChipPayment($purchase);
        }

        return new ChipPayment(new Payment($purchase));
    }

    /**
     * Create a new subscription builder.
     */
    public function subscription(BillableContract $billable, string $type, string | array $prices = []): SubscriptionBuilderContract
    {
        return new ChipSubscriptionBuilder($billable, $type, $prices);
    }

    /**
     * Create a new checkout session builder.
     */
    public function checkout(BillableContract $billable): CheckoutBuilderContract
    {
        return new ChipCheckoutBuilder($this, $billable);
    }

    /**
     * Retrieve a checkout session (purchase).
     */
    public function retrieveCheckout(string $sessionId): ?CheckoutContract
    {
        try {
            $purchase = $this->client()->getPurchase($sessionId);

            return new Chip\ChipCheckout($purchase);
        } catch (Throwable $e) {
            if ($this->isNotFoundException($e)) {
                return null;
            }

            Log::warning('Failed to retrieve CHIP checkout', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw GatewayRetrievalException::create('chip', 'checkout session', $sessionId, $e);
        }
    }

    /**
     * Retrieve a subscription.
     * Note: CHIP doesn't have native subscriptions, so we look up locally.
     */
    public function retrieveSubscription(string $subscriptionId): ?SubscriptionContract
    {
        try {
            $subscriptionQuery = OwnerScopedQuery::apply(CashierChip::$subscriptionModel::query());
            $subscription = $subscriptionQuery->whereKey($subscriptionId)->first();

            if (! $subscription) {
                return null;
            }

            return new ChipSubscription($subscription);
        } catch (Throwable $e) {
            Log::warning('Failed to retrieve CHIP subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            throw GatewayRetrievalException::create('chip', 'subscription', $subscriptionId, $e);
        }
    }

    /**
     * Retrieve a payment (purchase).
     */
    public function retrievePayment(string $paymentId): ?PaymentContract
    {
        try {
            $purchase = $this->client()->getPurchase($paymentId);

            if (! $this->purchaseBelongsToCurrentBillable($purchase)) {
                return null;
            }

            return new ChipPayment(new Payment($purchase));
        } catch (Throwable $e) {
            if ($this->isNotFoundException($e)) {
                return null;
            }

            Log::warning('Failed to retrieve CHIP payment', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw GatewayRetrievalException::create('chip', 'payment', $paymentId, $e);
        }
    }

    /**
     * Retrieve an invoice.
     * Note: CHIP uses purchases for invoices.
     */
    public function retrieveInvoice(string $invoiceId): ?InvoiceContract
    {
        try {
            $purchase = $this->client()->getPurchase($invoiceId);

            if (! $this->purchaseBelongsToCurrentBillable($purchase)) {
                return null;
            }

            return new Chip\ChipInvoice($purchase);
        } catch (Throwable $e) {
            if ($this->isNotFoundException($e)) {
                return null;
            }

            Log::warning('Failed to retrieve CHIP invoice', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            throw GatewayRetrievalException::create('chip', 'invoice', $invoiceId, $e);
        }
    }

    private function purchaseBelongsToCurrentBillable(PurchaseData $purchase): bool
    {
        $clientId = $purchase->getClientId();

        if ($clientId === null || mb_trim($clientId) === '') {
            return false;
        }

        try {
            return CashierChip::findBillable($clientId) instanceof BillableContract;
        } catch (Throwable) {
            return false;
        }
    }

    private function isNotFoundException(Throwable $e): bool
    {
        if ((int) $e->getCode() === 404) {
            return true;
        }

        if (method_exists($e, 'getStatusCode') && (int) $e->getStatusCode() === 404) {
            return true;
        }

        return $e instanceof ChipApiException
            && $e->getStatusCode() === 404;
    }

    /**
     * Get all subscriptions for a customer.
     *
     * @return Collection<int, SubscriptionContract>
     */
    public function subscriptions(BillableContract $billable): Collection
    {
        $subscriptionsRelation = $this->callBillableMethod($billable, 'subscriptions');

        $subscriptions = $subscriptionsRelation->get()
            ->map(fn ($subscription) => new ChipSubscription($subscription))
            ->values();

        /** @var Collection<int, SubscriptionContract> $subscriptions */
        return $subscriptions;
    }

    /**
     * Get all invoices for a customer.
     * Note: For CHIP, this returns purchase history.
     *
     * @param  bool|array<string, mixed>  $parameters  Either includePending bool or parameters array
     * @return Collection<int, InvoiceContract>
     */
    public function invoices(BillableContract $billable, bool | array $parameters = false): Collection
    {
        $rawInvoices = $this->callBillableMethod($billable, 'invoices');

        if (! is_iterable($rawInvoices)) {
            return collect();
        }

        /** @var iterable<int, mixed> $rawInvoices */
        $invoices = collect($rawInvoices)
            ->map(fn ($invoice) => new Chip\ChipInvoice($invoice))
            ->values();

        /** @var Collection<int, InvoiceContract> $invoices */
        return $invoices;
    }

    /**
     * Get all payment methods (recurring tokens) for a customer.
     *
     * @param  string|null  $type  Filter by payment method type (e.g., 'card')
     * @return Collection<int, PaymentMethodContract>
     */
    public function paymentMethods(BillableContract $billable, ?string $type = null): Collection
    {
        $rawPaymentMethods = $this->callBillableMethod($billable, 'paymentMethods');

        if (! is_iterable($rawPaymentMethods)) {
            return collect();
        }

        /** @var iterable<int, mixed> $rawPaymentMethods */
        $paymentMethods = collect($rawPaymentMethods)
            ->map(fn ($paymentMethod) => new ChipPaymentMethod($paymentMethod, $billable))
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

        return new ChipPaymentMethod($paymentMethod, $billable);
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

        return new ChipPaymentMethod($paymentMethod, $billable);
    }

    /**
     * Create a setup purchase for adding payment methods.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws InvalidArgumentException
     */
    public function createSetupIntent(BillableContract $billable, array $options = []): mixed
    {
        $idempotencyKey = $options['idempotency_key'] ?? null;

        if (! is_string($idempotencyKey) || mb_trim($idempotencyKey) === '') {
            throw new InvalidArgumentException(
                'An idempotency_key option is required when creating a CHIP setup intent.',
            );
        }

        $options['idempotency_key'] = mb_trim($idempotencyKey);

        // CHIP uses a zero-amount purchase with skip_capture for setup
        return $this->callBillableMethod($billable, 'createSetupPurchase', [$options]);
    }

    /**
     * Verify a webhook signature.
     *
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhookSignature(string $payload, array $headers): bool
    {
        $signature = $headers['X-Signature'] ?? $headers['x-signature'] ?? '';

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        try {
            $request = Request::create('/', 'POST', [], [], [], [
                'HTTP_X_SIGNATURE' => $signature,
            ], $payload);

            return app(WebhookService::class)->verifySignature($request);
        } catch (Throwable $e) {
            Log::error('CHIP webhook verification failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Handle a webhook event.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function handleWebhook(array $payload, array $headers = []): mixed
    {
        $event = $payload['event_type'] ?? null;

        if (! is_string($event) || $event === '') {
            throw new InvalidArgumentException('CHIP webhook payload must contain an event_type.');
        }

        return app(DispatchChipWebhookAction::class)->execute($event, $payload);
    }

    /**
     * Get the brand ID.
     */
    public function brandId(): string
    {
        return $this->getConfig('brand_id', $this->client()->getBrandId());
    }

    /**
     * Get available payment methods.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function availablePaymentMethods(array $filters = []): array
    {
        return $this->client()->getPaymentMethods($filters);
    }

    /**
     * Cancel a purchase.
     */
    public function cancelPurchase(string $purchaseId): mixed
    {
        return $this->client()->cancelPurchase($purchaseId);
    }

    /**
     * Capture a purchase.
     */
    public function capturePurchase(string $purchaseId, ?int $amount = null): mixed
    {
        return $this->client()->capturePurchase($purchaseId, $amount);
    }

    /**
     * Release a purchase.
     */
    public function releasePurchase(string $purchaseId): mixed
    {
        return $this->client()->releasePurchase($purchaseId);
    }

    /**
     * Get the customer billing portal URL.
     *
     * CHIP doesn't provide a hosted billing portal like Stripe, so we return
     * the route to our self-hosted billing panel.
     *
     * @param  array<string, mixed>  $options
     */
    public function customerPortalUrl(BillableContract $billable, string $returnUrl, array $options = []): string
    {
        $panelId = $options['panel'] ?? 'billing';
        $routeName = "filament.{$panelId}.pages.dashboard";

        if (! Route::has($routeName)) {
            return $returnUrl;
        }

        return route($routeName);
    }
}
