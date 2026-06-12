<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing;

use Illuminate\Support\ServiceProvider;

final class FilamentAddressingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filament-addressing.php',
            'filament-addressing',
        );

        $this->app->singleton(FilamentAddressingPlugin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/filament-addressing.php' => config_path('filament-addressing.php'),
        ], 'filament-addressing-config');
    }
}
