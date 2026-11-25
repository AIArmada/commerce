<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Commerce\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Events\Platform\CommerceIntegration;
use AIArmada\Orders\Events\OrderCanceled;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;

final class SyncEventOrderRegistrationsOnOrderCanceled
{
    public function __construct(
        private readonly SyncEventOrderRegistrationsAction $syncRegistrations,
    ) {}

    public function handle(OrderCanceled $event): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        $order = $event->order;
        $owner = $order->getRelationValue('owner');

        OwnerContext::withOwner($owner instanceof Model ? $owner : null, function () use ($event): void {
            $this->syncRegistrations->handle($event->order->getKey(), Order::class, 'cancelled');
        });
    }
}
