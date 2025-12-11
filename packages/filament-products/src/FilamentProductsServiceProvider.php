<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts;

use Illuminate\Support\ServiceProvider;

class FilamentProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-products'),
            ], 'filament-products-views');
        }
    }
}
