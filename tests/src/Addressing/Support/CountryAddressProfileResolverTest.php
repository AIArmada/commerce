<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
});

it('resolves a normal role to its hierarchy and level', function (): void {
    $definition = app(CountryAddressProfileResolver::class)->definitionForRole('MY', 'administrative_district');

    expect($definition)->not->toBeNull()
        ->and($definition['hierarchy']->key)->toBe('administrative')
        ->and($definition['level']->key)->toBe('district')
        ->and(app(CountryAddressProfileResolver::class)->levelForRole('MY', 'administrative_district')?->key)->toBe('district');
});

it('resolves the state_id pseudo-role to the state-kind level', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    $definition = $resolver->definitionForRole('MY', 'state_id');

    expect($definition)->not->toBeNull()
        ->and($definition['level']->kind)->toBe('state')
        ->and($definition['level']->key)->toBe($resolver->stateLevel('MY')?->key)
        ->and($resolver->levelForRole('MY', 'state_id')?->kind)->toBe('state');
});

it('accepts a country id as well as a country code', function (): void {
    $countryId = AddressCountry::query()->where('iso2', 'MY')->value('id');

    $definition = app(CountryAddressProfileResolver::class)->definitionForRole($countryId, 'administrative_district');

    expect($definition['level']->key ?? null)->toBe('district');
});

it('returns null for unknown roles', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->definitionForRole('MY', 'nope'))->toBeNull()
        ->and($resolver->levelForRole('MY', 'nope'))->toBeNull();
});

it('returns null for unknown countries', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->definitionForRole('XX', 'administrative_district'))->toBeNull()
        ->and($resolver->definitionForRole(null, 'administrative_district'))->toBeNull()
        ->and($resolver->levelForRole('XX', 'state_id'))->toBeNull();
});

it('resolves every provider area role with area kind and no other', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);
    $checked = 0;

    foreach (config('addressing.geography.providers', []) as $providerClass) {
        $provider = app($providerClass);

        if (! $provider instanceof CountryAddressProfile) {
            continue;
        }

        foreach ($provider->addressHierarchies() as $hierarchy) {
            foreach ($hierarchy->levels as $level) {
                $role = CountryAddressProfileResolver::roleForLevel($hierarchy, $level);
                $definition = $resolver->definitionForRole($provider->countryCode(), $role);

                if ($level->kind === 'state') {
                    expect($definition)->toBeNull();

                    continue;
                }

                expect($definition)->not->toBeNull()
                    ->and($definition['level']->kind)->toBe('area');

                $checked++;
            }
        }
    }

    expect($checked)->toBeGreaterThan(0);
});

it('resolves state_id to a state level only where providers define one', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    foreach (config('addressing.geography.providers', []) as $providerClass) {
        $provider = app($providerClass);

        if (! $provider instanceof CountryAddressProfile) {
            continue;
        }

        $hasStateLevel = false;

        foreach ($provider->addressHierarchies() as $hierarchy) {
            foreach ($hierarchy->levels as $level) {
                if ($level->kind === 'state') {
                    $hasStateLevel = true;

                    break 2;
                }
            }
        }

        $definition = $resolver->definitionForRole($provider->countryCode(), 'state_id');

        if ($hasStateLevel) {
            expect($definition)->not->toBeNull()
                ->and($definition['level']->kind)->toBe('state');
        } else {
            expect($definition)->toBeNull();
        }
    }
});

it('falls back to the hierarchy and level keys without an assignment role', function (): void {
    $hierarchy = new AddressHierarchyDefinition(key: 'administrative', label: 'Administrative', levels: []);
    $level = new AddressLevelDefinition(key: 'district', label: 'District', kind: 'area');

    expect(CountryAddressProfileResolver::roleForLevel($hierarchy, $level))->toBe('administrative_district');
});
