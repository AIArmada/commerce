<?php

declare(strict_types=1);

namespace AIArmada\Events;

use AIArmada\Events\Actions\CreateRegistrationsForOrderItemAction;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Actions\FinalizeOccurredEventOrdersAction;
use AIArmada\Events\Actions\FulfillEventOrderAction;
use AIArmada\Events\Actions\FulfillEventOrderItemAction;
use AIArmada\Events\Actions\SyncEventOrderCompletionAction;
use AIArmada\Events\Console\Commands\FinalizeEventOrdersCommand;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Listeners\SyncEventOrderCompletionOnRegistrationCheckedIn;
use AIArmada\Events\Services\RegistrationService;
use Illuminate\Contracts\Events\Dispatcher;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EventsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('events')
            ->hasConfigFile('events')
            ->hasCommand(FinalizeEventOrdersCommand::class)
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(CreateRegistrationsForOrderItemAction::class);
        $this->app->singleton(EnsureOccurrenceAction::class);
        $this->app->singleton(FinalizeOccurredEventOrdersAction::class);
        $this->app->bind(FulfillEventOrderAction::class);
        $this->app->bind(FulfillEventOrderItemAction::class);
        $this->app->singleton(RegistrationService::class);
        $this->app->singleton(SyncEventOrderCompletionAction::class);

        $dispatcher = $this->app->make(Dispatcher::class);
        $dispatcher->listen(RegistrationCheckedIn::class, SyncEventOrderCompletionOnRegistrationCheckedIn::class);
    }
}
