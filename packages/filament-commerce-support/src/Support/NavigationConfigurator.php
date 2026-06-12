<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Support;

use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;

class NavigationConfigurator
{
    public static function apply(): void
    {
        if (! config('filament-commerce-support.navigation.enabled', true)) {
            return;
        }

        $settings = app(CommerceNavigationSettings::class);

        if ($settings->groups !== []) {
            config()->set('commerce-support.filament.navigation.groups', array_merge(
                config('commerce-support.filament.navigation.groups', []),
                $settings->groups,
            ));
        }

        if ($settings->overrides !== []) {
            config()->set('commerce-support.filament.navigation.items', array_merge(
                config('commerce-support.filament.navigation.items', []),
                $settings->overrides,
            ));
        }
    }
}
