<?php

declare(strict_types=1);

namespace AIArmada\Stock;

use AIArmada\Stock\Console\CleanupExpiredReservationsCommand;
use AIArmada\Stock\Listeners\DeductStockOnPaymentSuccess;
use AIArmada\Stock\Listeners\ReleaseStockOnCartClear;
use AIArmada\Stock\Services\StockReservationService;
use AIArmada\Stock\Services\StockService;
use Illuminate\Support\Facades\Event;
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
     * Register cart package integration if available.
     */
    private function registerCartIntegration(): void
    {
        if (! config('stock.cart.enabled', true)) {
            return;
        }

        // Check if cart package is installed
        if (! class_exists(\AIArmada\Cart\CartManager::class)) {
            return;
        }

        // Register cart event listeners for stock release
        if (class_exists(\AIArmada\Cart\Events\CartCleared::class)) {
            Event::listen(
                \AIArmada\Cart\Events\CartCleared::class,
                [ReleaseStockOnCartClear::class, 'handleCleared']
            );
        }

        if (class_exists(\AIArmada\Cart\Events\CartDestroyed::class)) {
            Event::listen(
                \AIArmada\Cart\Events\CartDestroyed::class,
                [ReleaseStockOnCartClear::class, 'handleDestroyed']
            );
        }

        // Extend CartManager with stock functionality
        $this->app->extend('cart', function ($manager, $app) {
            if ($manager instanceof \AIArmada\Stock\Cart\CartManagerWithStock) {
                return $manager;
            }

            $proxy = \AIArmada\Stock\Cart\CartManagerWithStock::fromCartManager($manager);
            $proxy->setReservationService($app->make(StockReservationService::class));

            // Update container bindings
            $app->instance(\AIArmada\Cart\CartManager::class, $proxy);

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
        if (class_exists(\AIArmada\CashierChip\Events\PaymentSucceeded::class)) {
            Event::listen(
                \AIArmada\CashierChip\Events\PaymentSucceeded::class,
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

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            StockService::class,
            StockReservationService::class,
            'stock',
            'stock.reservations',
        ];
    }
}
