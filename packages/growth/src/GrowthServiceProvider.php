<?php

declare(strict_types=1);

namespace AIArmada\Growth;

use AIArmada\Growth\Actions\ProjectExperimentContextIntoSignalProperties;
use AIArmada\Growth\Actions\ResolveExperimentPreset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class GrowthServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('growth')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ResolveExperimentPreset::class);
        $this->app->singleton(ProjectExperimentContextIntoSignalProperties::class);
        $this->app->bind('growth.signal_event_property_enricher', ProjectExperimentContextIntoSignalProperties::class);
    }
}
