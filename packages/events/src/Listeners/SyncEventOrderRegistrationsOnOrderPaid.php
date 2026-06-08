<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Actions\FulfillEventOrderAction;
use AIArmada\Orders\Events\OrderPaid;

final class SyncEventOrderRegistrationsOnOrderPaid
{
    public function __construct(
        private readonly FulfillEventOrderAction $fulfillEventOrder,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->fulfillEventOrder->handle($event->order);
    }
}
