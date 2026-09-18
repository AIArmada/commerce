<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Aland\AlandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 16 Alander states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'AX')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '12',
        'name' => 'Brändö (legacy)',
        'label' => 'Brändö (legacy)',
    ]);

    app(AlandGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Brändö')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16);
});

it('maps every Alander state code to its area', function (): void {
    $mappings = app(AlandGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(16)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['12', '08', '03', '09', '10', '06', '02', '15', '14', '04', '13', '01', '05', '16', '07', '11']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AlandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Alander tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('AX');
    $country = AddressCountry::query()->where('iso2', 'AX')->firstOrFail();

    expect($result['seeded'])->toContain('AX')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(16)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(16);
});
