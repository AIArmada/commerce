<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud;

use AIArmada\Cart\Cart;
use DateTimeInterface;

/**
 * Context object containing all relevant information for fraud analysis.
 */
final readonly class FraudContext
{
    public function __construct(
        public Cart $cart,
        public ?string $userId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $sessionId,
        public DateTimeInterface $timestamp
    ) {}

    /**
     * Get cart identifier.
     */
    public function getCartId(): string
    {
        return $this->cart->getId();
    }

    /**
     * Get cart total in cents.
     */
    public function getCartTotal(): int
    {
        return $this->cart->getRawTotal();
    }

    /**
     * Get cart item count.
     */
    public function getItemCount(): int
    {
        return $this->cart->countItems();
    }

    /**
     * Get total quantity of items.
     */
    public function getTotalQuantity(): int
    {
        return $this->cart->getTotalQuantity();
    }

    /**
     * Check if user is authenticated.
     */
    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }

    /**
     * Convert to array for logging/storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cart_id' => $this->getCartId(),
            'cart_total' => $this->getCartTotal(),
            'item_count' => $this->getItemCount(),
            'total_quantity' => $this->getTotalQuantity(),
            'user_id' => $this->userId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'session_id' => $this->sessionId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'is_authenticated' => $this->isAuthenticated(),
        ];
    }
}
