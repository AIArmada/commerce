<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

use Spatie\LaravelData\Data;

final class PaymentRequest extends Data
{
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
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}

    /**
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
        );
    }
}
