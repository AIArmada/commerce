<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Concerns;

use Closure;

/**
 * Registers Singleton Aliases
 *
 * Provides helper methods for registering services as singletons
 * with optional aliases. Reduces boilerplate in service providers.
 */
/**
 * @phpstan-ignore trait.unused
 */
trait RegistersSingletonAliases
{
    /**
     * Register a singleton binding with an optional alias.
     *
     * @param  string  $abstract  The abstract class or interface name
     * @param  string|null  $alias  Optional alias (defaults to snake_case of class basename)
     * @param  Closure|null  $factory  Optional factory closure (defaults to simple instantiation)
     */
    protected function registerSingletonAlias(
        string $abstract,
        ?string $alias = null,
        ?Closure $factory = null
    ): void {
        // Register the singleton
        if ($factory) {
            $this->app->singleton($abstract, $factory);
        } else {
            $this->app->singleton($abstract);
        }

        // Register alias if provided
        if ($alias) {
            $this->app->alias($abstract, $alias);
        }
    }

    /**
     * Register multiple singleton aliases at once.
     *
     * @param  array<string, array{alias?: string, factory?: Closure}>  $bindings
     *
     * Example:
     * $this->registerSingletonAliases([
     *     CartService::class => ['alias' => 'cart'],
     *     StockService::class => ['alias' => 'stock', 'factory' => fn() => new StockService()],
     * ]);
     */
    protected function registerSingletonAliases(array $bindings): void
    {
        foreach ($bindings as $abstract => $config) {
            $this->registerSingletonAlias(
                $abstract,
                $config['alias'] ?? null,
                $config['factory'] ?? null
            );
        }
    }
}
