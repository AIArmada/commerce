<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport;

use AIArmada\CommerceSupport\Contracts\CommerceNavigationContributorInterface;
use AIArmada\CommerceSupport\Support\Filament\CommerceNavigation;
use AIArmada\FilamentCommerceSupport\Pages\ManageCommerceNavigation;
use AIArmada\FilamentCommerceSupport\Resources\CurrencyResource;
use AIArmada\FilamentCommerceSupport\Resources\LanguageResource;
use AIArmada\FilamentCommerceSupport\Resources\TimezoneResource;
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

        if ((bool) config('filament-commerce-support.navigation.enabled', true)) {
            $panel->pages([ManageCommerceNavigation::class]);
            $panel->resources($this->referenceResources());
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Reference-data resources. Filament discovery covers app paths only, so
     * the package must register its own resources explicitly.
     *
     * @return list<class-string>
     */
    private function referenceResources(): array
    {
        $resources = [];

        if ((bool) config('filament-commerce-support.resources.currencies.enabled', true)) {
            $resources[] = CurrencyResource::class;
        }

        if ((bool) config('filament-commerce-support.resources.languages.enabled', true)) {
            $resources[] = LanguageResource::class;
        }

        if ((bool) config('filament-commerce-support.resources.timezones.enabled', true)) {
            $resources[] = TimezoneResource::class;
        }

        return $resources;
    }
}
