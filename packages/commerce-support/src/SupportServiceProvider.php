<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Support Service Provider
 *
 * Foundation service provider for all AIArmada Commerce packages.
 * Provides core helper methods, utilities, and base functionality.
 */
final class SupportServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('commerce-support')
            ->hasCommands([
                Commands\SetupCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register any core services here if needed in the future
    }

    public function packageBooted(): void
    {
        // Boot any core functionality here if needed in the future
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [];
    }
}
