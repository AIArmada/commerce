<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Reservations;

use AIArmada\Seating\Contracts\SeatAllocatorInterface;
use AIArmada\Seating\Features\Reservations\Api\ReservationsApi;
use AIArmada\Seating\Features\Reservations\EntryPoints\Console\ReleaseExpiredHoldsCommand;
use AIArmada\Seating\Features\Reservations\Queries\EloquentReservationsApi;
use AIArmada\Seating\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Seating\Services\DefaultSeatAllocator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(SeatAllocatorInterface::class, DefaultSeatAllocator::class);
        $app->bind(ReservationsApi::class, EloquentReservationsApi::class);
    }

    public function boot(Application $app): void
    {
        if (! config('seating.scheduling.release_expired_holds', true)) {
            return;
        }

        $app->booted(static function () use ($app): void {
            $app->make(Schedule::class)->command(ReleaseExpiredHoldsCommand::class)->everyFiveMinutes();
        });
    }
}
