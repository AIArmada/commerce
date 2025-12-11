<?php

declare(strict_types=1);

namespace AIArmada\Orders;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/orders.php',
            'orders'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/orders.php' => config_path('orders.php'),
            ], 'orders-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'orders-migrations');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'orders');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'orders');

        $this->registerPolicies();
        $this->registerEventListeners();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Models\Order::class, Policies\OrderPolicy::class);
        Gate::policy(Models\OrderItem::class, Policies\OrderItemPolicy::class);
    }

    protected function registerEventListeners(): void
    {
        // Event listeners will be registered here
    }
}
