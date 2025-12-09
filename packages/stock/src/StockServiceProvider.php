<?php

declare(strict_types=1);

namespace AIArmada\Stock;

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Stock\Console\CleanupExpiredReservationsCommand;
use AIArmada\Stock\Listeners\DeductStockOnPaymentSuccess;
use AIArmada\Stock\Listeners\ReleaseStockOnCartClear;
use AIArmada\Stock\Services\StockReservationService;
use AIArmada\Stock\Services\StockService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class StockServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('stock')
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations()
            ->hasCommand(CleanupExpiredReservationsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->registerOwnerResolver();

        // Register Stock Service
        $this->app->singleton(StockService::class);
        $this->app->alias(StockService::class, 'stock');

        // Register Stock Reservation Service
        $this->app->singleton(StockReservationService::class);
        $this->app->alias(StockReservationService::class, 'stock.reservations');
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
            StockService::class,
            StockReservationService::class,
            OwnerResolverInterface::class,
            'stock',
            'stock.reservations',
        ];
    }

    /**
     * Register the owner resolver for multi-tenancy support.
     */
    private function registerOwnerResolver(): void
    {
        $this->app->singleton(OwnerResolverInterface::class, function (Application $app): OwnerResolverInterface {
            /** @var class-string<OwnerResolverInterface> $resolverClass */
            $resolverClass = config('stock.owner.resolver', NullOwnerResolver::class);

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
        if (! config('stock.cart.enabled', true)) {
            return;
        }

        // Check if cart package is installed
        if (! class_exists(CartManager::class)) {
            return;
        }

        // Register cart event listeners for stock release
        if (class_exists(CartCleared::class)) {
            Event::listen(
                CartCleared::class,
                [ReleaseStockOnCartClear::class, 'handleCleared']
            );
        }

        if (class_exists(CartDestroyed::class)) {
            Event::listen(
                CartDestroyed::class,
                [ReleaseStockOnCartClear::class, 'handleDestroyed']
            );
        }

        // Extend CartManager with stock functionality
        $this->app->extend('cart', function ($manager, $app) {
            if ($manager instanceof Cart\CartManagerWithStock) {
                return $manager;
            }

            $proxy = Cart\CartManagerWithStock::fromCartManager($manager);
            $proxy->setReservationService($app->make(StockReservationService::class));

            // Update container bindings
            $app->instance(CartManager::class, $proxy);
            $app->instance(CartManagerInterface::class, $proxy);

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
        if (! config('stock.payment.auto_deduct', true)) {
            return;
        }

        // CashierChip integration
        if (class_exists(PaymentSucceeded::class)) {
            Event::listen(
                PaymentSucceeded::class,
                DeductStockOnPaymentSuccess::class
            );
        }

        // Cashier (gateway-agnostic) integration
        if (class_exists(\AIArmada\Cashier\Events\PaymentSucceeded::class)) {
            Event::listen(
                \AIArmada\Cashier\Events\PaymentSucceeded::class,
                DeductStockOnPaymentSuccess::class
            );
        }

        // Generic payment success event (for custom implementations)
        $customEvents = config('stock.payment.events', []);

        foreach ($customEvents as $eventClass) {
            if (class_exists($eventClass)) {
                Event::listen($eventClass, DeductStockOnPaymentSuccess::class);
            }
        }
    }
}
