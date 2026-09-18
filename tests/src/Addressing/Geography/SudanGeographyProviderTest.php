<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Sudan\SudanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 18 Sudanese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SD')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'KH',
        'name' => 'Khar',
        'label' => 'Khar',
    ]);

    app(SudanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Khartoum')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(18);
});

it('maps every Sudanese state code to its area', function (): void {
    $mappings = app(SudanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(18)
        ->and(array_keys($mappings))->toBe(['DC', 'DE', 'DN', 'DS', 'DW', 'GD', 'GK', 'GZ', 'KA', 'KH', 'KN', 'KS', 'NB', 'NO', 'NR', 'NW', 'RS', 'SI']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SudanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Sudanese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SD');
    $country = AddressCountry::query()->where('iso2', 'SD')->firstOrFail();

    expect($result['seeded'])->toContain('SD')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(18)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(18);
});
