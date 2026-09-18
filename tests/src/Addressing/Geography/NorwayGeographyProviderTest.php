<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Norway\NorwayGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 17 Norwegian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'NO')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '42',
        'name' => 'Agder (legacy)',
        'label' => 'Agder (legacy)',
    ]);

    app(NorwayGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Agder')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(17);
});

it('maps every Norwegian state code to its area', function (): void {
    $mappings = app(NorwayGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(17)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['42', '32', '33', '56', '34', '22', '15', '18', '03', '31', '11', '21', '40', '55', '50', '39', '46']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NorwayGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Norwegian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('NO');
    $country = AddressCountry::query()->where('iso2', 'NO')->firstOrFail();

    expect($result['seeded'])->toContain('NO')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'arctic_region')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(17);
});
