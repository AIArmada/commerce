<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 9 South African states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'ZA')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'WC',
        'name' => 'WCape',
        'label' => 'WCape',
    ]);

    app(SouthAfricaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Western Cape')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9);
});

it('maps every South African state code to its area', function (): void {
    $mappings = app(SouthAfricaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(9)
        ->and(array_keys($mappings))->toBe(['EC', 'FS', 'GP', 'KZN', 'LP', 'MP', 'NC', 'NW', 'WC']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SouthAfricaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the South African tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('ZA');
    $country = AddressCountry::query()->where('iso2', 'ZA')->firstOrFail();

    expect($result['seeded'])->toContain('ZA')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(9)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});
