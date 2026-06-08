<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Orders\Events\OrderCanceled;

final class SyncEventOrderRegistrationsOnOrderCanceled
{
    public function __construct(
        private readonly SyncEventOrderRegistrationsAction $syncEventOrderRegistrations,
    ) {}

    public function handle(OrderCanceled $event): void
    {
        $this->syncEventOrderRegistrations->cancel($event->order, $event->reason);
    }
}
