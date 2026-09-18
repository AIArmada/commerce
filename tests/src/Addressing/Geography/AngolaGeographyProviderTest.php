<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Angola\AngolaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 18 Angolan states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'AO')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'LUA',
        'name' => 'Luanda City',
        'label' => 'Luanda City',
    ]);

    app(AngolaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Luanda')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(18);
});

it('maps every Angolan state code to its area', function (): void {
    $mappings = app(AngolaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(18)
        ->and(array_keys($mappings))->toBe(['BGO', 'BGU', 'BIE', 'CAB', 'CCU', 'CNN', 'CNO', 'CUS', 'HUA', 'HUI', 'LNO', 'LSU', 'LUA', 'MAL', 'MOX', 'NAM', 'UIG', 'ZAI']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AngolaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Angolan tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('AO');
    $country = AddressCountry::query()->where('iso2', 'AO')->firstOrFail();

    expect($result['seeded'])->toContain('AO')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(18)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(18);
});
