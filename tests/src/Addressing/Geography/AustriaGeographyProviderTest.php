<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Austria\AustriaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 9 Austrian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'AT')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '1',
        'name' => 'Burgenland (legacy)',
        'label' => 'Burgenland (legacy)',
    ]);

    app(AustriaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Burgenland')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9);
});

it('maps every Austrian state code to its area', function (): void {
    $mappings = app(AustriaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(9)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['1', '2', '3', '5', '6', '7', '4', '9', '8']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AustriaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Austrian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('AT');
    $country = AddressCountry::query()->where('iso2', 'AT')->firstOrFail();

    expect($result['seeded'])->toContain('AT')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(9)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});
