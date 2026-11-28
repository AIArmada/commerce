<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

/**
 * Contract for fulfillment/shipping integration.
 *
 * Implement this interface to connect orders with shipping management.
 *
 * The shipping package provides a ready-to-use implementation:
 * *
 * This implementation is automatically registered when both the orders
 * and shipping packages are installed together.
 */
interface FulfillmentHandler
{
    /**
     * Get the carriers exposed by the fulfillment integration.
     *
     * @return array<string, string>
     */
    public function availableCarriers(): array;

    /**
     * Create a shipment for an order.
     *
     * @param  array<string, mixed>  $shipmentData  Carrier, service, etc.
     * @return array{success: bool, shipment_id: ?string, tracking_number: ?string, error: ?string}
     */
    public function createShipment(string $orderId, array $shipmentData): array;

    /**
     * Get shipping rates for an order.
     *
     * @return array<array{carrier: string, service: string, rate: int, currency: string}>
     */
    public function getRates(string $orderId): array;

    /**
     * Get tracking info for a shipment.
     *
     * @return array{status: string, events: array<array{date: string, description: string, location: ?string}>}
     */
    public function getTracking(string $trackingNumber): array;
}
