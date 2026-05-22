<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\Registration;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

final class FulfillEventOrderAction
{
    public function __construct(
        private readonly FulfillEventOrderItemAction $fulfillEventOrderItem,
    ) {}

    /**
     * @return Collection<int, Registration>
     */
    public function handle(Order $order): Collection
    {
        $order->loadMissing(['items']);

        $registrations = new Collection;

        foreach ($order->items as $orderItem) {
            if (! $orderItem instanceof OrderItem) {
                continue;
            }

            $registrations = new Collection([
                ...$registrations->all(),
                ...$this->fulfillEventOrderItem->handle($order, $orderItem)->all(),
            ]);
        }

        return $registrations;
    }
}
