<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Exceptions;

final class InvalidCheckoutStateException extends CheckoutException
{
    public static function sessionExpired(string $sessionId): self
    {
        return new self(
            "Checkout session '{$sessionId}' has expired",
            ['session_id' => $sessionId],
        );
    }

    public static function sessionNotFound(string $sessionId): self
    {
        return new self(
            "Checkout session '{$sessionId}' not found",
            ['session_id' => $sessionId],
        );
    }

    public static function cannotModify(string $sessionId, string $status): self
    {
        return new self(
            "Cannot modify checkout session '{$sessionId}' in '{$status}' status",
            ['session_id' => $sessionId, 'status' => $status],
        );
    }

    public static function cannotCancel(string $sessionId, string $status): self
    {
        return new self(
            "Cannot cancel checkout session '{$sessionId}' in '{$status}' status",
            ['session_id' => $sessionId, 'status' => $status],
        );
    }

    public static function cartNotFound(string $cartId): self
    {
        return new self(
            "Cart '{$cartId}' not found for checkout",
            ['cart_id' => $cartId],
        );
    }

    public static function emptyCart(string $cartId): self
    {
        return new self(
            "Cart '{$cartId}' is empty",
            ['cart_id' => $cartId],
        );
    }
}
