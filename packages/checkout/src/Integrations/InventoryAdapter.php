<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Inventory\Contracts\CheckoutInventoryServiceInterface;

/**
 * Adapter for inventory package integration.
 *
 * Provides a consistent interface for checkout to interact with inventory,
 * gracefully falling back to unlimited stock when the inventory package
 * is not installed.
 */
final class InventoryAdapter
{
    /**
     * Get available stock for a product/variant.
     */
    public function getAvailableStock(string $productId, ?string $variantId = null): int
    {
        if (! $this->isInventoryPackageInstalled()) {
            return PHP_INT_MAX; // Unlimited stock when inventory package not installed
        }

        return $this->getInventoryService()->getAvailableStock($productId, $variantId);
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
        if (! $this->isInventoryPackageInstalled()) {
            return [
                'id' => 'mock_' . uniqid(),
                'expires_at' => now()->addSeconds($ttl)->toIso8601String(),
            ];
        }

        return $this->getInventoryService()->reserve(
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
        if (! $this->isInventoryPackageInstalled()) {
            return;
        }

        $this->getInventoryService()->releaseReservation($reservationId);
    }

    /**
     * Release all reservations for a reference (checkout session/cart).
     */
    public function releaseAllForReference(string $reference): int
    {
        if (! $this->isInventoryPackageInstalled()) {
            return 0;
        }

        return $this->getInventoryService()->releaseAllForReference($reference);
    }

    /**
     * Commit a reservation (convert to actual stock deduction).
     */
    public function commit(string $reservationId): void
    {
        if (! $this->isInventoryPackageInstalled()) {
            return;
        }

        $this->getInventoryService()->commitReservation($reservationId);
    }

    /**
     * Commit all reservations for a reference (checkout session/cart).
     */
    public function commitAllForReference(string $reference, ?string $orderId = null): int
    {
        if (! $this->isInventoryPackageInstalled()) {
            return 0;
        }

        return $this->getInventoryService()->commitAllForReference($reference, $orderId);
    }

    /**
     * Check if stock is available for multiple items.
     *
     * @param  array<array{product_id: string, variant_id: string|null, quantity: int}>  $items
     * @return array<string, array{available: bool, requested: int, stock: int}>
     */
    public function checkBulkAvailability(array $items): array
    {
        if (! $this->isInventoryPackageInstalled()) {
            $result = [];

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $variantId = $item['variant_id'] ?? null;
                $key = $variantId !== null ? "{$productId}:{$variantId}" : $productId;
                $result[$key] = [
                    'available' => true,
                    'requested' => $item['quantity'],
                    'stock' => PHP_INT_MAX,
                ];
            }

            return $result;
        }

        $result = $this->getInventoryService()->checkBulkAvailability($items);

        return $result['items'];
    }

    /**
     * Extend reservations for a reference.
     */
    public function extendReservations(string $reference, int $ttl): int
    {
        if (! $this->isInventoryPackageInstalled()) {
            return 0;
        }

        return $this->getInventoryService()->extendReservations($reference, $ttl);
    }

    /**
     * Check if the inventory package is installed.
     */
    private function isInventoryPackageInstalled(): bool
    {
        return interface_exists(CheckoutInventoryServiceInterface::class);
    }

    /**
     * Get the checkout inventory service from the container.
     */
    private function getInventoryService(): CheckoutInventoryServiceInterface
    {
        return app(CheckoutInventoryServiceInterface::class);
    }
}
