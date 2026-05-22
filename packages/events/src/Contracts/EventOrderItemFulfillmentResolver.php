<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventOrderItemFulfillment;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;

interface EventOrderItemFulfillmentResolver
{
    public function resolve(Order $order, OrderItem $orderItem): ?EventOrderItemFulfillment;
}
