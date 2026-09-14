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

        if (! is_array($groups)) {
            $groups = [];
        }

        if (! is_array($overrides)) {
            $overrides = [];
        }

        if ($groups !== []) {
            config()->set('commerce-support.filament.navigation.groups', self::mergeEntries(
                config('commerce-support.filament.navigation.groups', []),
                $groups,
            ));
        }

        if ($overrides !== []) {
            $groupRenames = [];
            foreach ($groups as $key => $groupConfig) {
                if (! is_array($groupConfig)) {
                    continue;
                }
                $newLabel = $groupConfig['label'] ?? $key;
                if (is_string($newLabel) && $newLabel !== '' && $newLabel !== $key) {
                    $groupRenames[$key] = $newLabel;
                }
            }

            if ($groupRenames !== []) {
                foreach ($overrides as &$itemConfig) {
                    if (! is_array($itemConfig)) {
                        continue;
                    }
                    $currentGroup = $itemConfig['group'] ?? '';
                    if (isset($groupRenames[$currentGroup])) {
                        $itemConfig['group'] = $groupRenames[$currentGroup];
                    }
                }
                unset($itemConfig);
            }

            config()->set('commerce-support.filament.navigation.items', self::mergeEntries(
                config('commerce-support.filament.navigation.items', []),
                $overrides,
            ));
        }
    }

    /**
     * Merge settings entries over file config per entry (not per top-level key).
     *
     * Settings rows persist partial configs (only submitted, differing keys),
     * so a shallow array_merge would permanently shadow file-level keys that
     * the settings entry omits (e.g. a file label lost when only sort was
     * saved). Non-array entries are corrupt payloads and are skipped.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function mergeEntries(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $override) {
            if (! is_array($override)) {
                continue;
            }

            $existing = $base[$key] ?? [];

            $base[$key] = is_array($existing)
                ? array_replace_recursive($existing, $override)
                : $override;
        }

        return $base;
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

    /**
     * Clear captured defaults so the next apply() re-reads file config.
     * Intended for long-lived workers after runtime config changes and for
     * test isolation; the captured statics otherwise never refresh in-process.
     */
    public static function reset(): void
    {
        self::$originalGroupConfig = [];
        self::$originalItemsConfig = [];
        self::$captured = false;
    }

    public static function resolveSettings(): ?CommerceNavigationSettings
    {
        try {
            $settings = app(CommerceNavigationSettings::class);

            // Settings load lazily on first property access: reading the
            // properties forces the load here so a missing table or missing
            // rows surfaces inside the catch instead of at some later,
            // unguarded read.
            $loaded = [$settings->groups, $settings->overrides];
            unset($loaded);

            return $settings;
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
