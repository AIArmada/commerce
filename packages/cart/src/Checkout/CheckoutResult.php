<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout;

use AIArmada\Cart\Cart;

/**
 * Result of a checkout pipeline execution.
 */
final readonly class CheckoutResult
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string>  $completedStages
     * @param  array<string, string>  $errors
     */
    public function __construct(
        public bool $success,
        public Cart $cart,
        public array $context = [],
        public array $completedStages = [],
        public array $errors = [],
        public ?string $orderId = null,
        public ?string $paymentUrl = null
    ) {}

    /**
     * Get a value from the context.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Check if checkout completed successfully.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Get the order ID if checkout was successful.
     */
    public function getOrderId(): ?string
    {
        return $this->orderId ?? ($this->context['order_id'] ?? null);
    }

    /**
     * Get the payment URL if a redirect is required.
     */
    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl ?? ($this->context['payment_url'] ?? null);
    }
}
