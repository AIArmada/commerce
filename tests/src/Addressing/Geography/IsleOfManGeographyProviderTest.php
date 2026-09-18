<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 6 Manx states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'IM')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Ayre (legacy)',
        'label' => 'Ayre (legacy)',
    ]);

    app(IsleOfManGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Ayre')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6);
});

it('maps every Manx state code to its area', function (): void {
    $mappings = app(IsleOfManGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(6)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '03', '04', '05', '06']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IsleOfManGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('sheadings')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Manx tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('IM');
    $country = AddressCountry::query()->where('iso2', 'IM')->firstOrFail();

    expect($result['seeded'])->toContain('IM')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'sheadings')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});
