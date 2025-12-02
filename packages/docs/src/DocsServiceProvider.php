<?php

declare(strict_types=1);

namespace AIArmada\Docs;

use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Docs\Services\DocService;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class DocsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('docs')
            ->hasConfigFile()
            ->hasViews()
            ->discoversMigrations()
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        // Numbering registry
        $this->app->singleton(Numbering\NumberStrategyRegistry::class, Numbering\ConfiguredNumberStrategyRegistry::class);

        // Register Doc Service
        $this->app->singleton(DocService::class);
        $this->app->alias(DocService::class, 'doc');
    }

    public function packageBooted(): void
    {
        $this->validateOwnerConfiguration();
    }

    /**
     * Validate owner configuration (fail-fast pattern)
     *
     * @throws RuntimeException If owner is enabled but resolver is not configured
     */
    protected function validateOwnerConfiguration(): void
    {
        if (! config('docs.owner.enabled', false)) {
            return;
        }

        $resolverClass = config('docs.owner.resolver', NullOwnerResolver::class);

        if (empty($resolverClass)) {
            throw new RuntimeException(
                'Docs owner is enabled but no resolver is configured. '.
                'Set DOCS_OWNER_RESOLVER or docs.owner.resolver to a class implementing OwnerResolverInterface.'
            );
        }

        if (! class_exists($resolverClass)) {
            throw new RuntimeException(
                "Docs owner resolver class '{$resolverClass}' does not exist."
            );
        }

        if (! is_subclass_of($resolverClass, OwnerResolverInterface::class) && $resolverClass !== NullOwnerResolver::class) {
            throw new RuntimeException(
                "Docs owner resolver '{$resolverClass}' must implement ".OwnerResolverInterface::class
            );
        }

        // Register the resolver in the container (only if not already bound)
        if (! $this->app->bound(OwnerResolverInterface::class)) {
            $this->app->singleton(OwnerResolverInterface::class, $resolverClass);
        }
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            DocService::class,
            'doc',
            Numbering\NumberStrategyRegistry::class,
        ];
    }
}
