<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart;

use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartConditionAdded as ConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved as ConditionRemoved;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemConditionAdded;
use AIArmada\Cart\Events\ItemConditionRemoved;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Models\RecoveryAttempt;
use AIArmada\Cart\Services\BuiltInRulesFactory;
use AIArmada\FilamentCart\Commands\AggregateMetricsCommand;
use AIArmada\FilamentCart\Commands\MarkAbandonedCartsCommand;
use AIArmada\FilamentCart\Commands\MonitorCartsCommand;
use AIArmada\FilamentCart\Commands\ProcessAlertsCommand;
use AIArmada\FilamentCart\Commands\ProcessRecoveryCommand;
use AIArmada\FilamentCart\Commands\ScheduleRecoveryCommand;
use AIArmada\FilamentCart\Listeners\ApplyGlobalConditions;
use AIArmada\FilamentCart\Listeners\CleanupSnapshotOnCartMerged;
use AIArmada\FilamentCart\Listeners\SyncCartOnEvent;
use AIArmada\FilamentCart\Models\Cart as CartSnapshot;
use AIArmada\FilamentCart\Services\AlertDispatcher;
use AIArmada\FilamentCart\Services\AlertEvaluator;
use AIArmada\FilamentCart\Services\CartAnalyticsService;
use AIArmada\FilamentCart\Services\CartConditionBatchRemoval;
use AIArmada\FilamentCart\Services\CartConditionValidator;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\FilamentCart\Services\CartMonitor;
use AIArmada\FilamentCart\Services\CartSyncManager;
use AIArmada\FilamentCart\Services\ExportService;
use AIArmada\FilamentCart\Services\MetricsAggregator;
use AIArmada\FilamentCart\Services\NormalizedCartSynchronizer;
use AIArmada\FilamentCart\Services\RecoveryAnalytics;
use AIArmada\FilamentCart\Services\RecoveryDispatcher;
use AIArmada\FilamentCart\Services\RecoveryScheduler;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentCartServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-cart')
            ->hasConfigFile('filament-cart')
            ->hasViews('filament-cart')
            ->hasCommands([
                AggregateMetricsCommand::class,
                ScheduleRecoveryCommand::class,
                ProcessRecoveryCommand::class,
                MonitorCartsCommand::class,
                ProcessAlertsCommand::class,
                MarkAbandonedCartsCommand::class,
            ])
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentCartPlugin::class);

        // Configure cart package to use filament Cart model for relationships
        config(['cart.models.cart' => CartSnapshot::class]);

        if (! $this->app->bound(RulesFactoryInterface::class)) {
            $this->app->singleton(function ($app): RulesFactoryInterface {
                $factoryClass = (string) config(
                    'filament-cart.dynamic_rules_factory',
                    BuiltInRulesFactory::class
                );

                return $app->make($factoryClass);
            });
        }

        $this->app->singleton(CartInstanceManager::class);
        $this->app->singleton(NormalizedCartSynchronizer::class);
        $this->app->singleton(CartSyncManager::class);
        $this->app->singleton(CartConditionValidator::class);
        $this->app->singleton(CartConditionBatchRemoval::class);

        // Analytics services
        $this->app->singleton(MetricsAggregator::class);
        $this->app->singleton(CartAnalyticsService::class);
        $this->app->singleton(ExportService::class);

        // Recovery services
        $this->app->singleton(RecoveryScheduler::class);
        $this->app->singleton(RecoveryDispatcher::class);
        $this->app->singleton(RecoveryAnalytics::class);

        // Monitoring & Alert services
        $this->app->singleton(CartMonitor::class);
        $this->app->singleton(AlertDispatcher::class);
        $this->app->singleton(AlertEvaluator::class);
    }

    public function bootingPackage(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../database/settings' => database_path('settings'),
        ], 'filament-cart-settings');
    }

    public function packageBooted(): void
    {
        $this->synchronizeOwnerScopeConfiguration();
        $this->registerEventListeners();
        $this->registerRecoveryAttemptCartRelation();
    }

    /**
     * Register the cart() relationship on RecoveryAttempt.
     *
     * This relationship is defined here rather than in core cart package
     * to avoid circular dependency (core cart should not depend on filament-cart).
     */
    protected function registerRecoveryAttemptCartRelation(): void
    {
        RecoveryAttempt::resolveRelationUsing('cart', function (RecoveryAttempt $model) {
            return $model->belongsTo(CartSnapshot::class, 'cart_id');
        });
    }

    protected function synchronizeOwnerScopeConfiguration(): void
    {
        $filamentOwnerEnabled = (bool) config('filament-cart.owner.enabled', false);
        $cartOwnerEnabled = (bool) config('cart.owner.enabled', false);

        $enabled = $filamentOwnerEnabled || $cartOwnerEnabled;

        if ($filamentOwnerEnabled) {
            $includeGlobal = (bool) config('filament-cart.owner.include_global', false);
        } elseif ($cartOwnerEnabled) {
            $includeGlobal = (bool) config('cart.owner.include_global', false);
        } else {
            $includeGlobal = false;
        }

        config()->set('filament-cart.owner.enabled', $enabled);
        config()->set('filament-cart.owner.include_global', $includeGlobal);
        config()->set('cart.owner.enabled', $enabled);
        config()->set('cart.owner.include_global', $includeGlobal);
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            NormalizedCartSynchronizer::class,
            CartSyncManager::class,
            MetricsAggregator::class,
            CartAnalyticsService::class,
            ExportService::class,
            RecoveryScheduler::class,
            RecoveryDispatcher::class,
            RecoveryAnalytics::class,
            CartMonitor::class,
            AlertDispatcher::class,
            AlertEvaluator::class,
        ];
    }

    /**
     * Register event listeners for cart synchronization
     */
    protected function registerEventListeners(): void
    {
        // Apply global conditions on cart creation and item changes
        // Note: We listen to specific events (ItemAdded, ItemUpdated, ItemRemoved) instead of CartUpdated
        // to avoid infinite loops when applying conditions triggers CartConditionAdded → CartUpdated
        $this->app['events']->listen(CartCreated::class, [ApplyGlobalConditions::class, 'handleCartCreated']);
        $this->app['events']->listen(ItemAdded::class, [ApplyGlobalConditions::class, 'handleItemChanged']);
        $this->app['events']->listen(ItemUpdated::class, [ApplyGlobalConditions::class, 'handleItemChanged']);
        $this->app['events']->listen(ItemRemoved::class, [ApplyGlobalConditions::class, 'handleItemChanged']);

        // Unified sync listener for all cart state changes
        // Handles: CartCreated, CartCleared, CartDestroyed, ItemAdded, ItemUpdated, ItemRemoved,
        //          CartConditionAdded, CartConditionRemoved, ItemConditionAdded, ItemConditionRemoved
        $this->app['events']->listen(
            [
                CartCreated::class,
                CartCleared::class,
                CartDestroyed::class,
                ItemAdded::class,
                ItemUpdated::class,
                ItemRemoved::class,
                ConditionAdded::class,
                ConditionRemoved::class,
                ItemConditionAdded::class,
                ItemConditionRemoved::class,
            ],
            SyncCartOnEvent::class
        );

        // Cart merge cleanup
        $this->app['events']->listen(CartMerged::class, CleanupSnapshotOnCartMerged::class);
    }
}
