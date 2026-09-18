<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Finland\FinlandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 18 Finnish states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'FI')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '08',
        'name' => 'Central Finland (legacy)',
        'label' => 'Central Finland (legacy)',
    ]);

    app(FinlandGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Central Finland')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(18);
});

it('maps every Finnish state code to its area', function (): void {
    $mappings = app(FinlandGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(18)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['08', '07', '19', '05', '09', '10', '13', '14', '15', '12', '16', '11', '17', '02', '03', '04', '06', '18']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(FinlandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Finnish tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('FI');
    $country = AddressCountry::query()->where('iso2', 'FI')->firstOrFail();

    expect($result['seeded'])->toContain('FI')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(18)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(18);
});
