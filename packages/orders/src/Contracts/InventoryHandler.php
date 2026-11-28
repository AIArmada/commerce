<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

/**
 * Contract for inventory integration.
 *
 * Implement this interface to connect orders with inventory management.
 */
interface InventoryHandler
{
    /**
     * Reserve inventory for an order.
     */
    public function reserveInventory(string $orderId): bool;

    /**
     * Deduct inventory for a paid order.
     */
    public function deductInventory(string $orderId): bool;

    /**
     * Release reserved inventory (on cancellation).
     */
    public function releaseInventory(string $orderId): bool;

    /**
     * Check if all items are in stock.
     */
    public function checkAvailability(string $orderId): bool;
}
