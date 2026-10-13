<?php

declare(strict_types=1);

namespace AIArmada\Membership;

use AIArmada\Membership\Console\Commands\SyncRolesCommand;
use AIArmada\Membership\Services\MembershipRoleSyncService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class MembershipServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('membership')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                SyncRolesCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MembershipRoleSyncService::class);
    }
}
