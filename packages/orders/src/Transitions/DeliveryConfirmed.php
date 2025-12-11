<?php

declare(strict_types=1);

namespace AIArmada\Orders\Transitions;

use AIArmada\Orders\Events\OrderDelivered;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Delivered;
use Spatie\ModelStates\Transition;

/**
 * Transition from Shipped → Delivered.
 *
 * This transition is triggered when delivery is confirmed.
 */
class DeliveryConfirmed extends Transition
{
    public function __construct(
        private Order $order,
        /** @var array<string, mixed> */
        private array $metadata = [],
    ) {}

    public function handle(): Order
    {
        // Update order state and delivered timestamp
        $this->order->status->transitionTo(Delivered::class);
        $this->order->delivered_at = now();
        $this->order->save();

        // Dispatch event
        event(new OrderDelivered($this->order));

        return $this->order;
    }
}
