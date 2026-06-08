<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Orders\Events\OrderRefunded;

final class SyncEventOrderRegistrationsOnOrderRefunded
{
    public function __construct(
        private readonly SyncEventOrderRegistrationsAction $syncEventOrderRegistrations,
    ) {}

    public function handle(OrderRefunded $event): void
    {
        $this->syncEventOrderRegistrations->refund($event->order, $event->amount, $event->reason, [
            'source' => 'order_refunded',
        ]);
    }
}
