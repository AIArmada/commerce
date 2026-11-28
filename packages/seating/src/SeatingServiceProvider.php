<?php

declare(strict_types=1);

namespace AIArmada\Seating;

use AIArmada\Seating\EntryPoints\Console\ReleaseExpiredHoldsCommand;
use AIArmada\Seating\Contracts\SeatAllocatorInterface;
use AIArmada\Seating\EntryPoints\Livewire\SeatMap;
use AIArmada\Seating\Services\DefaultSeatAllocator;
use AIArmada\Seating\Platform\ExtensionRegistry;
use AIArmada\Seating\Platform\FeatureCatalog;
use AIArmada\Seating\Platform\FeatureModule;
use Illuminate\Console\Scheduling\Schedule;
use RuntimeException;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class SeatingServiceProvider extends PackageServiceProvider
{
    /** @var list<FeatureModule> */
    private array $modules = [];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('seating')
            ->hasConfigFile()
            ->hasViews()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommand(ReleaseExpiredHoldsCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->app->bind(SeatAllocatorInterface::class, DefaultSeatAllocator::class);
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule');

            if (! $module instanceof FeatureModule) {
                throw new RuntimeException('Invalid seating feature module: ' . $feature);
            }

            $module->register($this->app);
            $this->modules[] = $module;
        }
    }

    public function bootingPackage(): void
    {
        foreach ($this->modules as $module) {
            $module->boot($this->app);
        }

        Livewire::component('seating.seat-map', SeatMap::class);

        if ((bool) config('seating.scheduling.release_expired_holds', true)) {
            $this->app->booted(function (): void {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command(ReleaseExpiredHoldsCommand::class)->everyFiveMinutes();
            });
        }
    }
}
