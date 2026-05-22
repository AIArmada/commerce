<?php

declare(strict_types=1);

namespace AIArmada\Events;

use AIArmada\Events\Actions\CreateRegistrationsForOrderItemAction;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Actions\FulfillEventOrderAction;
use AIArmada\Events\Actions\FulfillEventOrderItemAction;
use AIArmada\Events\Services\RegistrationService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EventsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('events')
            ->hasConfigFile('events')
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(CreateRegistrationsForOrderItemAction::class);
        $this->app->singleton(EnsureOccurrenceAction::class);
        $this->app->bind(FulfillEventOrderAction::class);
        $this->app->bind(FulfillEventOrderItemAction::class);
        $this->app->singleton(RegistrationService::class);
    }
}
