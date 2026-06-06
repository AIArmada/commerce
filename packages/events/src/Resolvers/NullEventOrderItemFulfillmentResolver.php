<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Data\EventOrderItemFulfillment;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;

final class NullEventOrderItemFulfillmentResolver implements EventOrderItemFulfillmentResolver
{
    public function resolve(Order $order, OrderItem $orderItem): ?EventOrderItemFulfillment
    {
        return null;
    }
}
