<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

/**
 * @phpstan-ignore trait.unused
 */
trait RegistersSingletonAliases
{
    /**
     * Register singleton services with aliases.
     *
     * Services defined in the $singletons property will be registered
     * as singletons in the container.
     */
    protected function registerSingletonAliases(): void
    {
        if (! property_exists($this, 'singletons')) {
            return;
        }

        foreach ($this->singletons as $alias => $concrete) {
            $this->app->singleton($alias, fn ($app) => $app->make($concrete));
        }
    }
}
