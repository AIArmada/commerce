<?php

declare(strict_types=1);

namespace AIArmada\Persons;

use AIArmada\Persons\Actions\SeedLanguagesAction;
use AIArmada\Persons\Commands\SeedLanguagesCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class PersonsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('persons')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands(
                SeedLanguagesCommand::class,
            );
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SeedLanguagesAction::class);
    }
}
