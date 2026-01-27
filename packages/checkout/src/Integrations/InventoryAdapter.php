<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

final class InventoryAdapter
{
    /**
     * Get available stock for a product/variant.
     */
    public function getAvailableStock(string $productId, ?string $variantId = null): int
    {
        if (! class_exists(\AIArmada\Inventory\Contracts\InventoryServiceInterface::class)) {
            return PHP_INT_MAX; // Unlimited stock when inventory package not installed
        }

        $inventoryService = app(\AIArmada\Inventory\Contracts\InventoryServiceInterface::class);

        return $inventoryService->getAvailableStock($productId, $variantId);
    }

    /**
     * Reserve stock for checkout.
     *
     * @return array{id: string, expires_at: string}
     */
    public function reserve(
        string $productId,
        ?string $variantId,
        int $quantity,
        string $reference,
        int $ttl = 900,
    ): array {
        if (! class_exists(\AIArmada\Inventory\Contracts\InventoryServiceInterface::class)) {
            return [
                'id' => 'mock_' . uniqid(),
                'expires_at' => now()->addSeconds($ttl)->toIso8601String(),
            ];
        }

        $inventoryService = app(\AIArmada\Inventory\Contracts\InventoryServiceInterface::class);

        return $inventoryService->reserve(
            productId: $productId,
            variantId: $variantId,
            quantity: $quantity,
            reference: $reference,
            ttl: $ttl,
        );
    }

    /**
     * Release a reservation.
     */
    public function release(string $reservationId): void
    {
        if (! class_exists(\AIArmada\Inventory\Contracts\InventoryServiceInterface::class)) {
            return;
        }

        $inventoryService = app(\AIArmada\Inventory\Contracts\InventoryServiceInterface::class);
        $inventoryService->releaseReservation($reservationId);
    }

    /**
     * Commit a reservation (convert to actual stock deduction).
     */
    public function commit(string $reservationId): void
    {
        if (! class_exists(\AIArmada\Inventory\Contracts\InventoryServiceInterface::class)) {
            return;
        }

        $inventoryService = app(\AIArmada\Inventory\Contracts\InventoryServiceInterface::class);
        $inventoryService->commitReservation($reservationId);
    }

    /**
     * Check if stock is available for multiple items.
     *
     * @param  array<array{product_id: string, variant_id: string|null, quantity: int}>  $items
     * @return array<string, array{available: bool, requested: int, stock: int}>
     */
    public function checkBulkAvailability(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $variantId = $item['variant_id'] ?? null;
            $quantity = $item['quantity'];

            $available = $this->getAvailableStock($productId, $variantId);

            $key = $variantId ? "{$productId}:{$variantId}" : $productId;
            $result[$key] = [
                'available' => $available >= $quantity,
                'requested' => $quantity,
                'stock' => $available,
            ];
        }

        return $result;
    }
}
