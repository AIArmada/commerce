<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 5 Tajik states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'TJ')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'DU',
        'name' => 'Dushanbe (legacy)',
        'label' => 'Dushanbe (legacy)',
    ]);

    app(TajikistanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Dushanbe')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5);
});

it('maps every Tajik state code to its area', function (): void {
    $mappings = app(TajikistanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(5)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['DU', 'GB', 'KT', 'RA', 'SU']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TajikistanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Tajik tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('TJ');
    $country = AddressCountry::query()->where('iso2', 'TJ')->firstOrFail();

    expect($result['seeded'])->toContain('TJ')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_territory')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_region')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'districts_under_republic_administration')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});
