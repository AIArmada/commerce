<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Support\Integration\CommerceIntegration;

final class SyncEventOrderRegistrationsOnOrderRefunded
{
    public function handle(object $event): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        OwnerContext::withOwner($event->order->owner ?? null, function () use ($event): void {
            $action = app(\AIArmada\Events\Actions\SyncEventOrderRegistrationsAction::class);
            $action->handle($event->order->id, \AIArmada\Orders\Models\Order::class, 'refunded');
        });
    }
}
