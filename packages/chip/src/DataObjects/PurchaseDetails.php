<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

final class PurchaseDetails
{
    public function __construct(
        public readonly string $currency,
        /** @var array<int, Product> */
        public readonly array $products,
        public readonly int $total,
        public readonly string $language,
        public readonly ?string $notes,
        public readonly int $debt,
        public readonly ?int $subtotal_override,
        public readonly ?int $total_tax_override,
        public readonly ?int $total_discount_override,
        public readonly ?int $total_override,
        /** @var array<string, mixed> */
        public readonly array $request_client_details,
        public readonly string $timezone,
        public readonly bool $due_strict,
        public readonly ?string $email_message,
        /** @var array<string, mixed>|null */
        public readonly ?array $metadata,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            currency: $data['currency'] ?? 'MYR',
            products: isset($data['products']) ? array_map(fn ($product) => Product::fromArray($product), $data['products']) : [],
            total: $data['total'] ?? 0,
            language: $data['language'] ?? 'en',
            notes: $data['notes'] ?? null,
            debt: $data['debt'] ?? 0,
            subtotal_override: $data['subtotal_override'] ?? null,
            total_tax_override: $data['total_tax_override'] ?? null,
            total_discount_override: $data['total_discount_override'] ?? null,
            total_override: $data['total_override'] ?? null,
            request_client_details: is_array($data['request_client_details'] ?? null) ? $data['request_client_details'] : [],
            timezone: $data['timezone'] ?? 'Asia/Kuala_Lumpur',
            due_strict: $data['due_strict'] ?? false,
            email_message: $data['email_message'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    public function getTotalInCurrency(): float
    {
        return $this->total / 100;
    }

    public function getSubtotalInCurrency(): float
    {
        return array_reduce($this->products, function ($carry, $product) {
            return $carry + ($product->price * (float) $product->quantity);
        }, 0) / 100;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'products' => array_map(fn ($product) => $product->toArray(), $this->products),
            'total' => $this->total,
            'language' => $this->language,
            'notes' => $this->notes,
            'debt' => $this->debt,
            'subtotal_override' => $this->subtotal_override,
            'total_tax_override' => $this->total_tax_override,
            'total_discount_override' => $this->total_discount_override,
            'total_override' => $this->total_override,
            'request_client_details' => $this->request_client_details,
            'timezone' => $this->timezone,
            'due_strict' => $this->due_strict,
            'email_message' => $this->email_message,
        ];
    }
}
