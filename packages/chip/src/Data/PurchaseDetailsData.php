<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Akaunting\Money\Money;

final readonly class PurchaseDetailsData
{
    public function __construct(
        public string $currency,
        /** @var array<int, ProductData> */
        public array $products,
        public Money $total,
        public string $language,
        public ?string $notes,
        public Money $debt,
        public ?Money $subtotal_override,
        public ?Money $total_tax_override,
        public ?Money $total_discount_override,
        public ?Money $total_override,
        /** @var array<string, mixed> */
        public array $request_client_details,
        public string $timezone,
        public bool $due_strict,
        public ?string $email_message,
        /** @var array<string, mixed>|null */
        public ?array $metadata,
    ) {}

    /**
     * Create PurchaseDetails from array data (typically from CHIP API response).
     * Amounts in the array are expected to be in cents (minor units).
     *
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $currency = $data['currency'] ?? 'MYR';

        return new self(
            currency: $currency,
            products: isset($data['products'])
                ? array_map(fn ($product) => ProductData::from($product + ['currency' => $currency]), $data['products'])
                : [],
            total: Money::{$currency}($data['total'] ?? 0),
            language: $data['language'] ?? 'en',
            notes: $data['notes'] ?? null,
            debt: Money::{$currency}($data['debt'] ?? 0),
            subtotal_override: isset($data['subtotal_override']) ? Money::{$currency}($data['subtotal_override']) : null,
            total_tax_override: isset($data['total_tax_override']) ? Money::{$currency}($data['total_tax_override']) : null,
            total_discount_override: isset($data['total_discount_override']) ? Money::{$currency}($data['total_discount_override']) : null,
            total_override: isset($data['total_override']) ? Money::{$currency}($data['total_override']) : null,
            request_client_details: is_array($data['request_client_details'] ?? null) ? $data['request_client_details'] : [],
            timezone: $data['timezone'] ?? 'UTC',
            due_strict: $data['due_strict'] ?? false,
            email_message: $data['email_message'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Get the total in cents for API communication.
     */
    public function getTotalInCents(): int
    {
        return (int) $this->total->getAmount();
    }

    /**
     * Get the calculated subtotal from products as Money.
     */
    public function getSubtotal(): Money
    {
        $subtotalCents = array_reduce($this->products, static fn (int $carry, ProductData $product): int => $carry + $product->getSubtotalInCents(), 0);

        return Money::{$this->currency}($subtotalCents);
    }

    /**
     * Get the subtotal in cents for API communication.
     */
    public function getSubtotalInCents(): int
    {
        return (int) $this->getSubtotal()->getAmount();
    }

    /**
     * Convert to array for CHIP API (amounts in cents).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'products' => array_map(fn ($product) => $product->toArray(), $this->products),
            'total' => $this->getTotalInCents(),
            'language' => $this->language,
            'notes' => $this->notes,
            'debt' => (int) $this->debt->getAmount(),
            'subtotal_override' => $this->subtotal_override ? (int) $this->subtotal_override->getAmount() : null,
            'total_tax_override' => $this->total_tax_override ? (int) $this->total_tax_override->getAmount() : null,
            'total_discount_override' => $this->total_discount_override ? (int) $this->total_discount_override->getAmount() : null,
            'total_override' => $this->total_override ? (int) $this->total_override->getAmount() : null,
            'request_client_details' => $this->request_client_details,
            'timezone' => $this->timezone,
            'due_strict' => $this->due_strict,
            'email_message' => $this->email_message,
            'metadata' => $this->metadata,
        ];
    }
}
