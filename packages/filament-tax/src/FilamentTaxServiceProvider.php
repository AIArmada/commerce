<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax;

use Illuminate\Support\ServiceProvider;

class FilamentTaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-tax');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filament-tax');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-tax'),
            ], 'filament-tax-views');

            $this->publishes([
                __DIR__ . '/../resources/lang' => lang_path('vendor/filament-tax'),
            ], 'filament-tax-lang');
        }
    }
}
