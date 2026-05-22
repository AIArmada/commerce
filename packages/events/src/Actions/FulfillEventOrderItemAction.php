<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Models\Registration;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

final class FulfillEventOrderItemAction
{
    public function __construct(
        private readonly EventOrderItemFulfillmentResolver $fulfillmentResolver,
        private readonly CreateRegistrationsForOrderItemAction $createRegistrationsForOrderItem,
    ) {}

    /**
     * @return Collection<int, Registration>
     */
    public function handle(Order $order, OrderItem $orderItem): Collection
    {
        $fulfillment = $this->fulfillmentResolver->resolve($order, $orderItem);

        if ($fulfillment === null) {
            return new Collection;
        }

        return $this->createRegistrationsForOrderItem->handle(
            $fulfillment->occurrence,
            $orderItem,
            $fulfillment->participants,
            $fulfillment->purchaser,
        );
    }
}
