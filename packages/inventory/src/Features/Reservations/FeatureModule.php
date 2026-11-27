<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations;

use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Cart\CartManagerWithInventory;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Listeners\CommitInventoryOnPayment;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Listeners\ReleaseInventoryOnCartClear;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Listeners\ValidateInventoryOnAdd;
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
        $this->registerCartIntegration($app);
        $this->registerPaymentIntegration();

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

    private function registerCartIntegration(Application $app): void
    {
        if (! config('inventory.cart.enabled', true)) {
            return;
        }

        // Check if cart package is installed
        if (! interface_exists(CartManagerInterface::class)) {
            return;
        }

        // Register cart event listeners for inventory release
        if (class_exists(CartCleared::class)) {
            Event::listen(
                CartCleared::class,
                [ReleaseInventoryOnCartClear::class, 'handleCleared']
            );
        }

        if (class_exists(CartDestroyed::class)) {
            Event::listen(
                CartDestroyed::class,
                [ReleaseInventoryOnCartClear::class, 'handleDestroyed']
            );
        }

        // Register ItemAdded listener for validation/auto-allocation
        if (class_exists(ItemAdded::class)) {
            Event::listen(
                ItemAdded::class,
                ValidateInventoryOnAdd::class
            );
        }

        // Extend CartManager with inventory functionality
        $app->extend('cart', function ($manager, $app) {
            if ($manager instanceof CartManagerWithInventory) {
                return $manager;
            }

            $proxy = CartManagerWithInventory::fromCartManager($manager);
            $proxy->setAllocationService($app->make(InventoryAllocationService::class));

            // Clear cached facade instance
            if (class_exists(Cart::class)) {
                Cart::clearResolvedInstance('cart');
            }

            return $proxy;
        });
    }

    /**
     * Register payment success listeners if payment packages are available.
     */
    private function registerPaymentIntegration(): void
    {
        if (! config('inventory.payment.auto_commit', true)) {
            return;
        }

        // CashierChip integration
        if (class_exists(PaymentSucceeded::class)) {
            Event::listen(
                PaymentSucceeded::class,
                CommitInventoryOnPayment::class
            );
        }

        // Cashier (gateway-agnostic) integration
        if (class_exists(\AIArmada\Cashier\Events\PaymentSucceeded::class)) {
            Event::listen(
                \AIArmada\Cashier\Events\PaymentSucceeded::class,
                CommitInventoryOnPayment::class
            );
        }

        // Generic payment success events (for custom implementations)
        $customEvents = config('inventory.payment.events', []);

        foreach ($customEvents as $eventClass) {
            if (class_exists($eventClass)) {
                Event::listen($eventClass, CommitInventoryOnPayment::class);
            }
        }
    }
}
