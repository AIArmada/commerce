<?php

declare(strict_types=1);

namespace AIArmada\Orders\Transitions;

use AIArmada\Orders\Events\OrderShipped;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Shipped;
use Spatie\ModelStates\Transition;

/**
 * Transition from Processing → Shipped.
 *
 * This transition is triggered when a shipment is created.
 * It records the shipping details and updates the order state.
 */
class ShipmentCreated extends Transition
{
    public function __construct(
        private Order $order,
        private string $carrier,
        private string $trackingNumber,
        private ?string $shipmentId = null,
        /** @var array<string, mixed> */
        private array $metadata = [],
    ) {}

    public function handle(): Order
    {
        // Update order state and shipped timestamp
        $this->order->status->transitionTo(Shipped::class);
        $this->order->shipped_at = now();
        $this->order->save();

        // Store shipping info in metadata if no dedicated shipment table
        $existingMetadata = $this->order->metadata ?? [];
        $this->order->metadata = array_merge($existingMetadata, [
            'shipping' => [
                'carrier' => $this->carrier,
                'tracking_number' => $this->trackingNumber,
                'shipment_id' => $this->shipmentId,
                'shipped_at' => now()->toIso8601String(),
            ],
        ]);
        $this->order->save();

        // Dispatch event
        event(new OrderShipped(
            $this->order,
            $this->carrier,
            $this->trackingNumber,
            $this->shipmentId
        ));

        return $this->order;
    }
}
