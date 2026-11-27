<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Conditions\EntryPoints\Listeners;

use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Features\Conditions\Actions\ReconcileGlobalConditions;

final readonly class ApplyGlobalConditions
{
    public function __construct(private ReconcileGlobalConditions $reconcile) {}

    public function handleCartCreated(CartCreated $event): void
    {
        $this->reconcile->execute($event->cart);
    }

    public function handleItemChanged(ItemAdded | ItemUpdated | ItemRemoved $event): void
    {
        $this->reconcile->execute($event->cart);
    }
}
