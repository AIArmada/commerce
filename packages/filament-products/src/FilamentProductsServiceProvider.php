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
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-products');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filament-products');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-products'),
            ], 'filament-products-views');

            $this->publishes([
                __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/filament-products'),
            ], 'filament-products-translations');
        }
    }
}
