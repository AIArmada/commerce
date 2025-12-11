<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders;

use Illuminate\Support\ServiceProvider;

class FilamentOrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-orders'),
            ], 'filament-orders-views');
        }
    }
}
