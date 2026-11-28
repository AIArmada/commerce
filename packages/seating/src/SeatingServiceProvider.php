<?php

declare(strict_types=1);

namespace AIArmada\Seating;

use AIArmada\Seating\EntryPoints\Livewire\SeatMap;
use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Maps\Models\SeatMap as SeatMapModel;
use AIArmada\Seating\Features\Maps\Models\SeatSection;
use AIArmada\Seating\Features\Reservations\EntryPoints\Console\ReleaseExpiredHoldsCommand;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
use AIArmada\Seating\Platform\ExtensionRegistry;
use AIArmada\Seating\Platform\FeatureCatalog;
use AIArmada\Seating\Platform\FeatureModule;
use Livewire\Livewire;
use RuntimeException;
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
        $modelDefaults = [
            'seating.models.seat_map' => SeatMapModel::class,
            'seating.models.seat_section' => SeatSection::class,
            'seating.models.seat' => Seat::class,
            'seating.models.seat_hold' => SeatHold::class,
            'seating.models.seat_allocation' => SeatAllocation::class,
        ];

        foreach ($modelDefaults as $key => $model) {
            if (! $this->app['config']->get($key)) {
                $this->app['config']->set($key, $model);
            }
        }

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

    }
}
