<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

use Spatie\LaravelData\Data;

final class PaymentRequest extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $client
     */
    public function __construct(
        public int $amount,
        public string $currency,
        public ?string $gateway = null,
        public ?string $description = null,
        public ?string $customerEmail = null,
        public ?string $customerName = null,
        public ?string $customerPhone = null,
        public ?string $successUrl = null,
        public ?string $failureUrl = null,
        public ?string $cancelUrl = null,
        public array $metadata = [],
        public ?string $paymentMethod = null,
        public ?string $provider = null,
        public ?string $idempotencyKey = null,
        public ?string $billableType = null,
        public ?string $billableId = null,
        /** @var array<string, mixed> */
        public array $client = [],
        public bool $clientFrozen = false,
        public ?string $providerCustomerId = null,
        public bool $providerCustomerFrozen = false,
        public bool $billableFrozen = false,
    ) {}

    /**
     * Build a request supplied directly by a host. This path is not an
     * attempt replay and therefore keeps the historical live-session client
     * behavior in provider builders.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: $data['amount'],
            currency: $data['currency'] ?? config('checkout.defaults.currency', 'MYR'),
            gateway: $data['gateway'] ?? null,
            description: $data['description'] ?? null,
            customerEmail: $data['customer_email'] ?? null,
            customerName: $data['customer_name'] ?? null,
            customerPhone: $data['customer_phone'] ?? null,
            successUrl: $data['success_url'] ?? null,
            failureUrl: $data['failure_url'] ?? null,
            cancelUrl: $data['cancel_url'] ?? null,
            metadata: $data['metadata'] ?? [],
            paymentMethod: $data['payment_method'] ?? null,
            provider: $data['provider'] ?? null,
            idempotencyKey: $data['idempotency_key'] ?? null,
            billableType: $data['billable_type'] ?? null,
            billableId: $data['billable_id'] ?? null,
            client: is_array($data['client'] ?? null) ? $data['client'] : [],
            clientFrozen: ($data['client_frozen'] ?? false) === true,
            providerCustomerId: is_string($data['provider_customer_id'] ?? null)
                ? $data['provider_customer_id']
                : null,
            providerCustomerFrozen: ($data['provider_customer_frozen'] ?? false) === true,
            billableFrozen: ($data['billable_frozen'] ?? false) === true,
        );
    }

    /**
     * Reconstruct the exact request frozen into the attempt claim. Missing
     * required fields are an incomplete claim, not permission to fall back to
     * mutable session data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromSnapshot(array $data): self
    {
        return new self(
            amount: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? ''),
            gateway: is_string($data['gateway'] ?? null) ? $data['gateway'] : null,
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
            customerEmail: is_string($data['customer_email'] ?? null) ? $data['customer_email'] : null,
            customerName: is_string($data['customer_name'] ?? null) ? $data['customer_name'] : null,
            customerPhone: is_string($data['customer_phone'] ?? null) ? $data['customer_phone'] : null,
            successUrl: is_string($data['success_url'] ?? null) ? $data['success_url'] : null,
            failureUrl: is_string($data['failure_url'] ?? null) ? $data['failure_url'] : null,
            cancelUrl: is_string($data['cancel_url'] ?? null) ? $data['cancel_url'] : null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            paymentMethod: is_string($data['payment_method'] ?? null) ? $data['payment_method'] : null,
            provider: is_string($data['provider'] ?? null) ? $data['provider'] : null,
            idempotencyKey: is_string($data['idempotency_key'] ?? null) ? $data['idempotency_key'] : null,
            billableType: is_string($data['billable_type'] ?? null) ? $data['billable_type'] : null,
            billableId: is_string($data['billable_id'] ?? null) ? $data['billable_id'] : null,
            client: is_array($data['client'] ?? null) ? $data['client'] : [],
            clientFrozen: ($data['client_frozen'] ?? false) === true,
            providerCustomerId: is_string($data['provider_customer_id'] ?? null)
                ? $data['provider_customer_id']
                : null,
            providerCustomerFrozen: ($data['provider_customer_frozen'] ?? false) === true,
            billableFrozen: ($data['billable_frozen'] ?? false) === true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'gateway' => $this->gateway,
            'description' => $this->description,
            'customer_email' => $this->customerEmail,
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'success_url' => $this->successUrl,
            'failure_url' => $this->failureUrl,
            'cancel_url' => $this->cancelUrl,
            'metadata' => $this->metadata,
            'payment_method' => $this->paymentMethod,
            'provider' => $this->provider,
            'idempotency_key' => $this->idempotencyKey,
            'billable_type' => $this->billableType,
            'billable_id' => $this->billableId,
            'client' => $this->client,
            'client_frozen' => $this->clientFrozen,
            'provider_customer_id' => $this->providerCustomerId,
            'provider_customer_frozen' => $this->providerCustomerFrozen,
            'billable_frozen' => $this->billableFrozen,
        ];
    }
}
