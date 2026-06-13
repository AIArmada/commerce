<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Support;

use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;

class NavigationConfigurator
{
    /**
     * Pre-merge config values, captured once on the first apply() call
     * so getTrueDefaultGroups() / getTrueDefaultOverrides() can read the
     * genuine file-based defaults without settings taint.
     */
    public static array $originalGroupConfig = [];

    public static array $originalItemsConfig = [];

    private static bool $captured = false;

    public static function apply(): void
    {
        if (! config('filament-commerce-support.navigation.enabled', true)) {
            return;
        }

        if (! self::$captured) {
            self::$originalGroupConfig = config('commerce-support.filament.navigation.groups', []);
            self::$originalItemsConfig = config('commerce-support.filament.navigation.items', []);
            self::$captured = true;
        }

        $settings = app(CommerceNavigationSettings::class);

        if ($settings->groups !== []) {
            config()->set('commerce-support.filament.navigation.groups', array_merge(
                config('commerce-support.filament.navigation.groups', []),
                $settings->groups,
            ));
        }

        if ($settings->overrides !== []) {
            $groupRenames = [];
            foreach ($settings->groups as $key => $groupConfig) {
                $newLabel = $groupConfig['label'] ?? $key;
                if (is_string($newLabel) && $newLabel !== '' && $newLabel !== $key) {
                    $groupRenames[$key] = $newLabel;
                }
            }

            $overrides = $settings->overrides;

            if ($groupRenames !== []) {
                foreach ($overrides as &$itemConfig) {
                    $currentGroup = $itemConfig['group'] ?? '';
                    if (isset($groupRenames[$currentGroup])) {
                        $itemConfig['group'] = $groupRenames[$currentGroup];
                    }
                }
                unset($itemConfig);
            }

            config()->set('commerce-support.filament.navigation.items', array_merge(
                config('commerce-support.filament.navigation.items', []),
                $overrides,
            ));
        }
    }
}
