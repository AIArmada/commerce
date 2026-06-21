<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications;

use Illuminate\Support\ServiceProvider;

final class FilamentCommunicationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filament-communications.php',
            'filament-communications',
        );

        $this->app->singleton(FilamentCommunicationsPlugin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/filament-communications.php' => config_path('filament-communications.php'),
        ], 'filament-communications-config');
    }
}
