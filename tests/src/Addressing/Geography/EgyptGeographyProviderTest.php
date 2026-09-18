<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Egypt\EgyptGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 27 Egyptian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'EG')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'C',
        'name' => 'Cai',
        'label' => 'Cai',
    ]);

    app(EgyptGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Cairo')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(27);
});

it('maps every Egyptian state code to its area', function (): void {
    $mappings = app(EgyptGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(27)
        ->and(array_keys($mappings))->toBe(['ALX', 'ASN', 'AST', 'BA', 'BH', 'BNS', 'C', 'DK', 'DT', 'FYM', 'GH', 'GZ', 'IS', 'JS', 'KB', 'KFS', 'KN', 'LX', 'MN', 'MNF', 'MT', 'PTS', 'SHG', 'SHR', 'SIN', 'SUZ', 'WAD']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(EgyptGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Egyptian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('EG');
    $country = AddressCountry::query()->where('iso2', 'EG')->firstOrFail();

    expect($result['seeded'])->toContain('EG')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(27)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(27)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(27);
});
