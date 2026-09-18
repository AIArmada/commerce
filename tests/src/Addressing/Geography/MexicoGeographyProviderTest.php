<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Mexico\MexicoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 32 Mexican states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MX')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'CMX',
        'name' => 'Mexico City',
        'label' => 'Mexico City',
    ]);

    app(MexicoGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Ciudad de México')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(32);
});

it('maps every Mexican state code to its area', function (): void {
    $mappings = app(MexicoGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(32)
        ->and(array_keys($mappings))->toBe(['AGU', 'BCN', 'BCS', 'CAM', 'CHH', 'CHP', 'CMX', 'COA', 'COL', 'DUR', 'GRO', 'GUA', 'HID', 'JAL', 'MEX', 'MIC', 'MOR', 'NAY', 'NLE', 'OAX', 'PUE', 'QUE', 'ROO', 'SIN', 'SLP', 'SON', 'TAB', 'TAM', 'TLA', 'VER', 'YUC', 'ZAC']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MexicoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Mexican tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MX');
    $country = AddressCountry::query()->where('iso2', 'MX')->firstOrFail();

    expect($result['seeded'])->toContain('MX')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(32)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(32)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(32);
});
