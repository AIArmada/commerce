<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Orders\Models\Order;

final class SyncEventOrderRegistrationsOnOrderRefunded
{
    public function handle(object $event): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        OwnerContext::withOwner($event->order->owner ?? null, function () use ($event): void {
            $action = app(SyncEventOrderRegistrationsAction::class);
            $action->handle($event->order->id, Order::class, 'refunded');
        });
    }
}
