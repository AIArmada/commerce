<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Myanmar\MyanmarGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 15 Myanmar states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MM')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '15',
        'name' => 'Mon State',
        'label' => 'Mon State',
    ]);

    app(MyanmarGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Mon')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(15);
});

it('maps every Myanmar state code to its area', function (): void {
    $mappings = app(MyanmarGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(15)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '03', '04', '05', '06', '07', '11', '12', '13', '14', '15', '16', '17', '18']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MyanmarGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Myanmar tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MM');
    $country = AddressCountry::query()->where('iso2', 'MM')->firstOrFail();

    expect($result['seeded'])->toContain('MM')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'union_territory')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(15);
});
