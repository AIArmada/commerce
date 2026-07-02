<?php

declare(strict_types=1);

namespace AIArmada\Seating;

use AIArmada\Seating\Console\Commands\ReleaseExpiredHoldsCommand;
use AIArmada\Seating\Contracts\SeatAllocatorInterface;
use AIArmada\Seating\Livewire\SeatMap;
use AIArmada\Seating\Services\DefaultSeatAllocator;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class SeatingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('seating')
            ->hasConfigFile()
            ->hasViews()
            ->runsMigrations()
            ->hasCommand(ReleaseExpiredHoldsCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->app->bind(SeatAllocatorInterface::class, fn (): DefaultSeatAllocator => new DefaultSeatAllocator);
    }

    public function bootingPackage(): void
    {
        Livewire::component('seating.seat-map', SeatMap::class);

        if ((bool) config('seating.scheduling.release_expired_holds', true)) {
            $this->app->booted(function (): void {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command(ReleaseExpiredHoldsCommand::class)->everyFiveMinutes();
            });
        }
    }
}
