<?php

declare(strict_types=1);

namespace AIArmada\Inventory;

use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Inventory\Cart\CartManagerWithInventory;
use AIArmada\Inventory\Console\CleanupExpiredAllocationsCommand;
use AIArmada\Inventory\Listeners\CommitInventoryOnPayment;
use AIArmada\Inventory\Listeners\ReleaseInventoryOnCartClear;
use AIArmada\Inventory\Services\InventoryAllocationService;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class InventoryServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('inventory')
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations()
            ->hasCommand(CleanupExpiredAllocationsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->registerOwnerResolver();

        // Register Inventory Service
        $this->app->singleton(InventoryService::class);
        $this->app->alias(InventoryService::class, 'inventory');

        // Register Inventory Allocation Service
        $this->app->singleton(InventoryAllocationService::class);
        $this->app->alias(InventoryAllocationService::class, 'inventory.allocations');
    }

    public function packageBooted(): void
    {
        $this->registerCartIntegration();
        $this->registerPaymentIntegration();
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            InventoryService::class,
            InventoryAllocationService::class,
            OwnerResolverInterface::class,
            'inventory',
            'inventory.allocations',
        ];
    }

    /**
     * Register the owner resolver for multi-tenancy support.
     */
    private function registerOwnerResolver(): void
    {
        $this->app->singleton(OwnerResolverInterface::class, function (\Illuminate\Contracts\Foundation\Application $app): OwnerResolverInterface {
            /** @var class-string<OwnerResolverInterface> $resolverClass */
            $resolverClass = config('inventory.owner.resolver', NullOwnerResolver::class);

            $resolver = $app->make($resolverClass);

            if (! $resolver instanceof OwnerResolverInterface) {
                throw new InvalidArgumentException(
                    sprintf('%s must implement %s', $resolverClass, OwnerResolverInterface::class)
                );
            }

            return $resolver;
        });
    }

    /**
     * Register cart package integration if available.
     */
    private function registerCartIntegration(): void
    {
        if (! config('inventory.cart.enabled', true)) {
            return;
        }

        // Check if cart package is installed
        if (! class_exists(\AIArmada\Cart\CartManager::class)) {
            return;
        }

        // Register cart event listeners for inventory release
        if (class_exists(\AIArmada\Cart\Events\CartCleared::class)) {
            Event::listen(
                \AIArmada\Cart\Events\CartCleared::class,
                [ReleaseInventoryOnCartClear::class, 'handleCleared']
            );
        }

        if (class_exists(\AIArmada\Cart\Events\CartDestroyed::class)) {
            Event::listen(
                \AIArmada\Cart\Events\CartDestroyed::class,
                [ReleaseInventoryOnCartClear::class, 'handleDestroyed']
            );
        }

        // Extend CartManager with inventory functionality
        $this->app->extend('cart', function ($manager, $app) {
            if ($manager instanceof CartManagerWithInventory) {
                return $manager;
            }

            $proxy = CartManagerWithInventory::fromCartManager($manager);
            $proxy->setAllocationService($app->make(InventoryAllocationService::class));

            // Update container bindings
            $app->instance(\AIArmada\Cart\CartManager::class, $proxy);
            $app->instance(\AIArmada\Cart\Contracts\CartManagerInterface::class, $proxy);

            // Clear cached facade instance
            if (class_exists(\AIArmada\Cart\Facades\Cart::class)) {
                \AIArmada\Cart\Facades\Cart::clearResolvedInstance('cart');
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
        if (class_exists(\AIArmada\CashierChip\Events\PaymentSucceeded::class)) {
            Event::listen(
                \AIArmada\CashierChip\Events\PaymentSucceeded::class,
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
