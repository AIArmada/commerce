<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers;

use Illuminate\Support\ServiceProvider;

class FilamentCustomersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-customers'),
            ], 'filament-customers-views');
        }
    }
}
