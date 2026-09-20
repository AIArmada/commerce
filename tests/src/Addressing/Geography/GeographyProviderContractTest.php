<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressAreaData;

/**
 * Generic invariants over every configured geography provider.
 *
 * These replace the per-country hierarchy-literal and exact-count tests:
 * provider data changes legitimately over time, so pinning literal shapes
 * or row counts per country only produces false failures. What must hold
 * for every provider is structural: valid hierarchy definitions, parseable
 * area sources without orphans, successful seeding, and metadata that only
 * references shipped areas.
 *
 * @return list<class-string>
 */
function geographyProviderClasses(): array
{
    $providers = config('addressing.geography.providers', []);

    expect($providers)->toBeArray()->not->toBeEmpty();

    return array_values(array_filter($providers, is_string(...)));
}

it('exposes a valid hierarchy shape for every configured provider', function (): void {
    $failures = [];

    foreach (geographyProviderClasses() as $providerClass) {
        $provider = app($providerClass);

        if (! $provider instanceof CountryGeographyProvider) {
            $failures[] = "{$providerClass} does not implement CountryGeographyProvider.";

            continue;
        }

        $code = $provider->countryCode();

        if ($provider->providerKey() === '') {
            $failures[] = "{$code}: provider key is empty.";
        }

        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            $failures[] = "{$code}: country code must be two uppercase letters.";
        }

        $hierarchies = $provider->addressHierarchies();

        if ($hierarchies === []) {
            $failures[] = "{$code}: no address hierarchies defined.";

            continue;
        }

        foreach ($hierarchies as $index => $hierarchy) {
            if ($hierarchy->key === '') {
                $failures[] = "{$code}: hierarchy #{$index} has an empty key.";
            }

            if ($hierarchy->levels === []) {
                $failures[] = "{$code}: hierarchy [{$hierarchy->key}] defines no levels.";

                continue;
            }

            $levelKeys = [];

            foreach ($hierarchy->levels as $level) {
                if ($level->key === '') {
                    $failures[] = "{$code}: hierarchy [{$hierarchy->key}] has a level with an empty key.";
                }

                if (! in_array($level->kind, ['state', 'area'], true)) {
                    $failures[] = "{$code}: level [{$level->key}] has an unexpected kind [{$level->kind}].";
                }

                $levelKeys[] = $level->key;
            }

            foreach ($hierarchy->levels as $level) {
                if ($level->parentKey !== null && ! in_array($level->parentKey, $levelKeys, true)) {
                    $failures[] = "{$code}: level [{$level->key}] references an unknown parent [{$level->parentKey}].";
                }
            }
        }
    }

    expect($failures)->toBe([]);
});

it('parses every provider area source without orphaned rows', function (): void {
    $failures = [];

    foreach (geographyProviderClasses() as $providerClass) {
        $provider = app($providerClass);

        if (! $provider instanceof CountryHierarchyProvider) {
            continue;
        }

        $code = $provider instanceof CountryGeographyProvider ? $provider->countryCode() : $providerClass;
        $areas = $provider->addressAreaSource()->areas()->all();

        if ($areas === []) {
            $failures[] = "{$code}: area source yields no areas.";

            continue;
        }

        $known = [];

        foreach ($areas as $area) {
            if (! $area instanceof AddressAreaData) {
                $failures[] = "{$code}: area source yields non-AddressAreaData rows.";

                continue 2;
            }

            if ($area->sourceId === '' || $area->name === '' || $area->type === '') {
                $failures[] = "{$code}: area row is missing sourceId, name, or type.";
            }

            if ($area->countryCode !== $code) {
                $failures[] = "{$code}: area [{$area->sourceId}] carries country [{$area->countryCode}].";
            }

            $known[$area->sourceId] = true;
        }

        foreach ($areas as $area) {
            if ($area->parentSourceId !== null && ! isset($known[$area->parentSourceId])) {
                $failures[] = "{$code}: area [{$area->sourceId}] references an unknown parent [{$area->parentSourceId}].";
            }
        }
    }

    expect($failures)->toBe([]);
});
