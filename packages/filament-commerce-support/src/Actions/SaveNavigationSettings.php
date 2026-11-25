<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Actions;

use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;
use AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator;

final readonly class SaveNavigationSettings
{
    /**
     * Persist the submitted navigation groups and item overrides, then rebuild
     * the runtime navigation config from the genuine file defaults.
     *
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, array<string, mixed>>  $overrides
     */
    public function execute(CommerceNavigationSettings $settings, array $groups, array $overrides): void
    {
        $settings->groups = $groups;
        $settings->overrides = $overrides;
        $settings->save();

        NavigationConfigurator::restoreGenuineDefaults();
        NavigationConfigurator::apply();
    }
}
