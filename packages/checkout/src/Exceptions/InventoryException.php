<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Exceptions;

final class InventoryException extends CheckoutException
{
    public static function insufficientStock(string $productId, int $requested, int $available): self
    {
        return new self(
            "Insufficient stock for product '{$productId}': requested {$requested}, available {$available}",
            ['product_id' => $productId, 'requested' => $requested, 'available' => $available],
        );
    }

    public static function reservationFailed(string $productId, string $reason): self
    {
        return new self(
            "Failed to reserve inventory for product '{$productId}': {$reason}",
            ['product_id' => $productId, 'reason' => $reason],
        );
    }

    public static function reservationExpired(string $reservationId): self
    {
        return new self(
            "Inventory reservation '{$reservationId}' has expired",
            ['reservation_id' => $reservationId],
        );
    }

    public static function releaseFailed(string $reservationId, string $reason): self
    {
        return new self(
            "Failed to release inventory reservation '{$reservationId}': {$reason}",
            ['reservation_id' => $reservationId, 'reason' => $reason],
        );
    }
}
