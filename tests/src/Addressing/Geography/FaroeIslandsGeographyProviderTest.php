<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\FaroeIslands\FaroeIslandsGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 6 Faroese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'FO')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'EY',
        'name' => 'Eysturoy (legacy)',
        'label' => 'Eysturoy (legacy)',
    ]);

    app(FaroeIslandsGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Eysturoy')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6);
});

it('maps every Faroese state code to its area', function (): void {
    $mappings = app(FaroeIslandsGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(6)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['EY', 'NO', 'SA', 'ST', 'SU', 'VA']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(FaroeIslandsGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Faroese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('FO');
    $country = AddressCountry::query()->where('iso2', 'FO')->firstOrFail();

    expect($result['seeded'])->toContain('FO')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});
