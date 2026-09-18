<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\SanMarino\SanMarinoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 9 Sammarinese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SM')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Acquaviva (legacy)',
        'label' => 'Acquaviva (legacy)',
    ]);

    app(SanMarinoGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Acquaviva')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9);
});

it('maps every Sammarinese state code to its area', function (): void {
    $mappings = app(SanMarinoGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(9)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '06', '02', '03', '04', '05', '08', '07', '09']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SanMarinoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Sammarinese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SM');
    $country = AddressCountry::query()->where('iso2', 'SM')->firstOrFail();

    expect($result['seeded'])->toContain('SM')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(9)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});
