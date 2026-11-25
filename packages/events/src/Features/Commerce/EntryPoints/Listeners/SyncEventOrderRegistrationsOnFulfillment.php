<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Commerce\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Events\Platform\CommerceIntegration;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;

final class SyncEventOrderRegistrationsOnFulfillment
{
    public function __construct(
        private readonly SyncEventOrderRegistrationsAction $syncRegistrations,
    ) {}

    public function handle(OrderFulfillmentRequired $event): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        $order = $event->order;
        $owner = $order->getRelationValue('owner');

        OwnerContext::withOwner($owner instanceof Model ? $owner : null, function () use ($event): void {
            // The event carries no payment claim: free orders must not
            // relabel their 'free' registrations as paid.
            $this->syncRegistrations->handle(
                $event->order->getKey(),
                Order::class,
                $event->gateway === 'free' ? 'free' : 'paid',
            );
        });
    }
}
