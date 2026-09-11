<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Support;

use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;
use Illuminate\Database\QueryException;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

class NavigationConfigurator
{
    /**
     * Pre-merge config values, captured once on the first apply() call
     * so getTrueDefaultGroups() / getTrueDefaultOverrides() can read the
     * genuine file-based defaults without settings taint.
     */
    private static array $originalGroupConfig = [];

    private static array $originalItemsConfig = [];

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

        $settings = self::resolveSettings();

        if ($settings === null) {
            return;
        }

        $groups = self::resolveSetting($settings, 'groups', []);
        $overrides = self::resolveSetting($settings, 'overrides', []);

        if ($groups !== []) {
            config()->set('commerce-support.filament.navigation.groups', array_merge(
                config('commerce-support.filament.navigation.groups', []),
                $groups,
            ));
        }

        if ($overrides !== []) {
            $groupRenames = [];
            foreach ($groups as $key => $groupConfig) {
                $newLabel = $groupConfig['label'] ?? $key;
                if (is_string($newLabel) && $newLabel !== '' && $newLabel !== $key) {
                    $groupRenames[$key] = $newLabel;
                }
            }

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

    /**
     * @return array<string, mixed>
     */
    public static function getOriginalGroupConfig(): array
    {
        return self::$originalGroupConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getOriginalItemsConfig(): array
    {
        return self::$originalItemsConfig;
    }

    /**
     * Restore the genuine file-based config before applying newly saved settings.
     */
    public static function restoreGenuineDefaults(): void
    {
        if (! self::$captured) {
            return;
        }

        config()->set('commerce-support.filament.navigation.groups', self::$originalGroupConfig);
        config()->set('commerce-support.filament.navigation.items', self::$originalItemsConfig);
    }

    private static function resolveSettings(): ?CommerceNavigationSettings
    {
        try {
            return app(CommerceNavigationSettings::class);
        } catch (QueryException | MissingSettings) {
            return null;
        }
    }

    private static function resolveSetting(CommerceNavigationSettings $settings, string $property, mixed $default = []): mixed
    {
        try {
            return $settings->{$property};
        } catch (QueryException | MissingSettings) {
            return $default;
        }
    }
}
