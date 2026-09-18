<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Albania\AlbaniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 12 Albanian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'AL')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Berat (legacy)',
        'label' => 'Berat (legacy)',
    ]);

    app(AlbaniaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Berat')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12);
});

it('maps every Albanian state code to its area', function (): void {
    $mappings = app(AlbaniaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(12)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '09', '02', '03', '04', '05', '06', '07', '08', '10', '11', '12']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AlbaniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Albanian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('AL');
    $country = AddressCountry::query()->where('iso2', 'AL')->firstOrFail();

    expect($result['seeded'])->toContain('AL')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});
