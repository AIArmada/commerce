<?php

declare(strict_types=1);

namespace AIArmada\Organizations;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Organizations\Contracts\CurrentOrganizationResolver;
use AIArmada\Organizations\Contracts\OrganizationAuthorization;
use AIArmada\Organizations\Contracts\OrganizationLifecycleHook;
use AIArmada\Organizations\Contracts\OrganizationPresentation;
use AIArmada\Organizations\Contracts\OrganizationVisibilityTransitionAuthorizer;
use AIArmada\Organizations\Resolvers\DefaultOrganizationAuthorization;
use AIArmada\Organizations\Resolvers\DefaultOrganizationPresentation;
use AIArmada\Organizations\Resolvers\DefaultOrganizationVisibilityTransitionAuthorizer;
use AIArmada\Organizations\Resolvers\NullCurrentOrganizationResolver;
use AIArmada\Organizations\Resolvers\NullOrganizationLifecycleHook;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class OrganizationsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('organizations')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function registeringPackage(): void
    {
        $resolver = (string) config('organizations.resolver', NullCurrentOrganizationResolver::class);

        $this->app->singleton(CurrentOrganizationResolver::class, $resolver);
        $this->app->singleton(OwnerResolverInterface::class, fn ($app): CurrentOrganizationResolver => $app->make(CurrentOrganizationResolver::class));
        $this->app->bind(OrganizationAuthorization::class, DefaultOrganizationAuthorization::class);
        $this->app->bind(OrganizationVisibilityTransitionAuthorizer::class, DefaultOrganizationVisibilityTransitionAuthorizer::class);
        $this->app->bind(OrganizationLifecycleHook::class, NullOrganizationLifecycleHook::class);
        $this->app->bind(OrganizationPresentation::class, DefaultOrganizationPresentation::class);
    }
}
