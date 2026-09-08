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
use Illuminate\Foundation\Application;
use LogicException;
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
        $this->app->bind(OrganizationAuthorization::class, DefaultOrganizationAuthorization::class);
        $this->app->bind(OrganizationVisibilityTransitionAuthorizer::class, DefaultOrganizationVisibilityTransitionAuthorizer::class);
        $this->app->bind(OrganizationLifecycleHook::class, NullOrganizationLifecycleHook::class);
        $this->app->bind(OrganizationPresentation::class, DefaultOrganizationPresentation::class);
    }

    public function packageBooted(): void
    {
        $this->app->singleton(CurrentOrganizationResolver::class, function (Application $app): CurrentOrganizationResolver {
            $resolver = config('organizations.resolver', NullCurrentOrganizationResolver::class);

            if (! is_string($resolver) || ! class_exists($resolver) || ! is_a($resolver, CurrentOrganizationResolver::class, true)) {
                throw new LogicException(sprintf(
                    'organizations.resolver must name a class implementing %s.',
                    CurrentOrganizationResolver::class,
                ));
            }

            $instance = $app->make($resolver);

            if (! $instance instanceof CurrentOrganizationResolver) {
                throw new LogicException(sprintf(
                    'The configured organizations resolver %s could not be resolved as %s.',
                    $resolver,
                    CurrentOrganizationResolver::class,
                ));
            }

            if ($instance instanceof NullCurrentOrganizationResolver
                && (bool) config('organizations.middleware.require_context', true)) {
                throw new LogicException(
                    'organizations.middleware.require_context is enabled, but organizations.resolver is still NullCurrentOrganizationResolver. Configure an application resolver before resolving the current organization.',
                );
            }

            return $instance;
        });

        $this->app->singleton(
            OwnerResolverInterface::class,
            fn (Application $app): CurrentOrganizationResolver => $app->make(CurrentOrganizationResolver::class),
        );
    }
}
