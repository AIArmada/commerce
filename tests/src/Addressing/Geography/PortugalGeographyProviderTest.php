<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Portugal\PortugalGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 20 Portuguese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'PT')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '20',
        'name' => 'Açores (legacy)',
        'label' => 'Açores (legacy)',
    ]);

    app(PortugalGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Açores')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(20);
});

it('maps every Portuguese state code to its area', function (): void {
    $mappings = app(PortugalGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(20)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['20', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '30', '12', '13', '14', '15', '16', '17', '18']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PortugalGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Portuguese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('PT');
    $country = AddressCountry::query()->where('iso2', 'PT')->firstOrFail();

    expect($result['seeded'])->toContain('PT')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_region')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(20);
});
