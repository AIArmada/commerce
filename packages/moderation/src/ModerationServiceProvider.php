<?php

declare(strict_types=1);

namespace AIArmada\Moderation;

use AIArmada\Moderation\Actions\BlockEntityAction;
use AIArmada\Moderation\Actions\RecordModerationAction;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ModerationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('moderation')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(BlockEntityAction::class);
        $this->app->singleton(RecordModerationAction::class);
    }

    public function bootingPackage(): void
    {
        //
    }
}
