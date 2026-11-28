<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Actions\Concerns\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\OrderFulfillment;
use AIArmada\Orders\Models\Order;
use InvalidArgumentException;

final readonly class OrderFulfillmentOperations implements OrderFulfillment
{
    use AssertsOrderOwnerBoundary;

    public function ship(string $orderId, string $carrier, string $trackingNumber, ?string $shipmentId = null, array $metadata = []): void
    {
        if (mb_trim($carrier) === '' || mb_trim($trackingNumber) === '') {
            throw new InvalidArgumentException('Shipping carrier and tracking number are required.');
        }

        new ShipmentCreated($this->resolve($orderId), $carrier, $trackingNumber, $shipmentId, $metadata)->handle();
    }

    public function confirmDelivery(string $orderId, array $metadata = []): void
    {
        new DeliveryConfirmed($this->resolve($orderId), $metadata)->handle();
    }

    public function complete(string $orderId, array $metadata = []): void
    {
        new OrderCompleted($this->resolve($orderId), $metadata)->handle();
    }

    public function cancel(string $orderId, string $reason, ?string $canceledBy = null): void
    {
        if (mb_trim($reason) === '') {
            throw new InvalidArgumentException('A cancellation reason is required.');
        }

        new OrderCanceled($this->resolve($orderId), $reason, $canceledBy)->handle();
    }

    private function resolve(string $orderId): Order
    {
        $order = Order::query()->findOrFail($orderId);
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        return $order;
    }
}
