<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 8 Slovak states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SK')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BC',
        'name' => 'Banská Bystrica (legacy)',
        'label' => 'Banská Bystrica (legacy)',
    ]);

    app(SlovakiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Banská Bystrica')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(8);
});

it('maps every Slovak state code to its area', function (): void {
    $mappings = app(SlovakiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(8)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BC', 'BL', 'KI', 'NI', 'PV', 'TC', 'TA', 'ZI']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SlovakiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Slovak tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SK');
    $country = AddressCountry::query()->where('iso2', 'SK')->firstOrFail();

    expect($result['seeded'])->toContain('SK')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(8)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(8);
});
