<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Montenegro\MontenegroGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 25 Montenegrin states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'ME')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Andrijevica (legacy)',
        'label' => 'Andrijevica (legacy)',
    ]);

    app(MontenegroGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Andrijevica')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(25);
});

it('maps every Montenegrin state code to its area', function (): void {
    $mappings = app(MontenegroGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(25)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '03', '04', '05', '07', '22', '08', '09', '10', '11', '12', '06', '23', '13', '14', '15', '16', '17', '18', '19', '24', '20', '21', '25']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MontenegroGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Montenegrin tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('ME');
    $country = AddressCountry::query()->where('iso2', 'ME')->firstOrFail();

    expect($result['seeded'])->toContain('ME')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(25)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(25);
});
