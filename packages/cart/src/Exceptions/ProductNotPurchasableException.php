<?php

declare(strict_types=1);

namespace AIArmada\Cart\Exceptions;

/**
 * Exception thrown when a product cannot be added to the cart.
 */
final class ProductNotPurchasableException extends CartException
{
    public function __construct(
        public readonly string $productId,
        public readonly string $productName,
        public readonly string $reason,
        public readonly ?int $requestedQuantity = null,
        public readonly ?int $availableStock = null,
    ) {
        $message = sprintf(
            'Product "%s" (ID: %s) cannot be added to cart: %s',
            $productName,
            $productId,
            $reason
        );

        if ($requestedQuantity !== null && $availableStock !== null) {
            $message .= sprintf(' (requested: %d, available: %d)', $requestedQuantity, $availableStock);
        }

        parent::__construct($message);
    }

    /**
     * Create exception for out of stock product.
     */
    public static function outOfStock(string $productId, string $productName, int $requested, int $available): self
    {
        return new self(
            productId: $productId,
            productName: $productName,
            reason: 'Insufficient stock',
            requestedQuantity: $requested,
            availableStock: $available
        );
    }

    /**
     * Create exception for inactive product.
     */
    public static function inactive(string $productId, string $productName): self
    {
        return new self(
            productId: $productId,
            productName: $productName,
            reason: 'Product is not available for purchase'
        );
    }

    /**
     * Create exception for minimum quantity not met.
     */
    public static function minimumNotMet(string $productId, string $productName, int $requested, int $minimum): self
    {
        return new self(
            productId: $productId,
            productName: $productName,
            reason: sprintf('Minimum quantity is %d', $minimum),
            requestedQuantity: $requested
        );
    }

    /**
     * Create exception for maximum quantity exceeded.
     */
    public static function maximumExceeded(string $productId, string $productName, int $requested, int $maximum): self
    {
        return new self(
            productId: $productId,
            productName: $productName,
            reason: sprintf('Maximum quantity is %d', $maximum),
            requestedQuantity: $requested
        );
    }

    /**
     * Create exception for invalid quantity increment.
     */
    public static function invalidIncrement(string $productId, string $productName, int $requested, int $increment): self
    {
        return new self(
            productId: $productId,
            productName: $productName,
            reason: sprintf('Quantity must be in increments of %d', $increment),
            requestedQuantity: $requested
        );
    }
}
