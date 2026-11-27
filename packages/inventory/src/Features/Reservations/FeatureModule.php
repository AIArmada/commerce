<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations;

use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Listeners\DeductInventoryFromOrder;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Listeners\ReleaseInventoryFromOrder;
use AIArmada\Inventory\Features\Reservations\Persistence\CheckoutReservationService;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Orders\Events\InventoryDeductionRequired;
use AIArmada\Orders\Events\InventoryReleaseRequired;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(InventoryAllocationService::class);
        $app->alias(InventoryAllocationService::class, 'inventory.allocations');
        $app->singleton(CheckoutReservationService::class);
        $app->bind(CheckoutReservationServiceInterface::class, CheckoutReservationService::class);
    }

    public function boot(Application $app): void
    {
        if (! config('inventory.orders.enabled', true)) {
            return;
        }

        if (class_exists(InventoryDeductionRequired::class)) {
            Event::listen(InventoryDeductionRequired::class, DeductInventoryFromOrder::class);
        }

        if (class_exists(InventoryReleaseRequired::class)) {
            Event::listen(InventoryReleaseRequired::class, ReleaseInventoryFromOrder::class);
        }
    }
}
