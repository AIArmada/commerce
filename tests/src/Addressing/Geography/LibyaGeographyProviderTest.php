<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Libya\LibyaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 22 Libyan states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'LY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BU',
        'name' => 'Al Butnan (legacy)',
        'label' => 'Al Butnan (legacy)',
    ]);

    app(LibyaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Al Butnan')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(22);
});

it('maps every Libyan state code to its area', function (): void {
    $mappings = app(LibyaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(22)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BU', 'WA', 'BA', 'DR', 'GT', 'JA', 'JG', 'JI', 'JU', 'KF', 'MJ', 'MI', 'MB', 'MQ', 'NL', 'NQ', 'SB', 'SR', 'TB', 'WD', 'WS', 'ZA']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LibyaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('popularate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Libyan tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('LY');
    $country = AddressCountry::query()->where('iso2', 'LY')->firstOrFail();

    expect($result['seeded'])->toContain('LY')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'popularate')->count())->toBe(22)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(22);
});
