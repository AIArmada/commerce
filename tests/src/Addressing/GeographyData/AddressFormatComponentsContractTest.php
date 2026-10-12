<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;

/**
 * @return list<string>
 */
function formatComponentKeys(mixed $node): array
{
    $keys = [];

    if (is_string($node)) {
        $segments = explode(':', $node);

        if (array_shift($segments) === 'components' && isset($segments[0]) && $segments[0] !== '') {
            return [$segments[0]];
        }

        return [];
    }

    if (is_array($node)) {
        foreach ($node as $key => $value) {
            if (in_array($key, ['lit', 'sep', 'display', 'country'], true)) {
                continue;
            }

            foreach (formatComponentKeys($value) as $found) {
                $keys[] = $found;
            }
        }
    }

    return $keys;
}

it('references only provider-known component keys in every format definition', function (): void {
    // Writer-computed keys that no provider defines. Writers live in consumer
    // repos (bulk loader, Eloquent import); each entry here must name its
    // writer or it does not belong.
    $syntheticKeys = [
        // MY ruled 2026-10-08: town area name when the subdivision is
        // town-like, else the daerah. Written by address writers (masjid
        // bulk loader addr_components; Eloquent import paths).
        'postcode_partner',
        // IT: 2-letter province code ("20121 Milano MI"), deterministically
        // suppliable from any writer's province data. No provider level
        // carries codes (levels carry names), so this key is writer-owned.
        'province_code',
    ];

    $formats = json_decode(
        (string) file_get_contents(__DIR__ . '/../../../../packages/addressing/resources/data/address-formats.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $providersByCountry = [];

    foreach (config('addressing.geography.providers', []) as $providerClass) {
        $provider = app($providerClass);

        if (! $provider instanceof CountryGeographyProvider) {
            continue;
        }

        $providersByCountry[mb_strtoupper(mb_trim($provider->countryCode()))][] = $provider;
    }

    $failures = [];

    foreach ($formats as $code => $definition) {
        $refs = array_unique(array_merge(
            formatComponentKeys($definition['lines'] ?? []),
            formatComponentKeys($definition['summary'] ?? []),
        ));

        if ($refs === []) {
            continue;
        }

        $known = [];

        foreach ($providersByCountry[$code] ?? [] as $provider) {
            if (! $provider instanceof CountryHierarchyProvider) {
                continue;
            }

            foreach ($provider->addressHierarchies() as $hierarchy) {
                foreach ($hierarchy->levels as $level) {
                    $known[$level->key] = true;
                    $known[$level->areaType ?? ''] = true;

                    foreach ($level->areaTypes as $type) {
                        $known[$type] = true;
                    }
                }
            }
        }

        unset($known['']);

        foreach ($refs as $ref) {
            if (isset($known[$ref]) || in_array($ref, $syntheticKeys, true)) {
                continue;
            }

            $failures[] = "{$code}: components:{$ref} is neither a provider level/area type nor a documented synthetic key.";
        }
    }

    expect($failures)->toBe([]);
});
