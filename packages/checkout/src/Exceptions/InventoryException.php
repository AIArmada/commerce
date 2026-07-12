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

    public static function reservationFailed(string $reference, string $reason): self
    {
        return new self(
            "Failed to reserve inventory for reference '{$reference}': {$reason}",
            ['reference' => $reference, 'reason' => $reason],
        );
    }

    public static function referenceNotFound(string $reference): self
    {
        return new self(
            "Inventory reservation reference '{$reference}' not found",
            ['reference' => $reference],
        );
    }

    public static function releaseFailed(string $reference, string $reason): self
    {
        return new self(
            "Failed to release inventory for reference '{$reference}': {$reason}",
            ['reference' => $reference, 'reason' => $reason],
        );
    }
}
