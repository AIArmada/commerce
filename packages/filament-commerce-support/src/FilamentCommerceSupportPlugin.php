<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport;

use AIArmada\CommerceSupport\Contracts\CommerceNavigationContributorInterface;
use AIArmada\CommerceSupport\Support\Filament\CommerceNavigation;
use AIArmada\FilamentCommerceSupport\Pages\ManageCommerceNavigation;
use AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Contracts\Container\Container;

class FilamentCommerceSupportPlugin implements Plugin
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-commerce-support';
    }

    public function register(Panel $panel): void
    {
        NavigationConfigurator::apply();

        CommerceNavigation::configurePanel($panel);

        foreach ($this->container->tagged('commerce.navigation.contributors') as $contributor) {
            if ($contributor instanceof CommerceNavigationContributorInterface) {
                $contributor->contribute($panel);
            }
        }

        $panel->pages([ManageCommerceNavigation::class]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
