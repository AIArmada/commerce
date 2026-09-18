<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 7 Santomean states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'ST')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Água Grande (legacy)',
        'label' => 'Água Grande (legacy)',
    ]);

    app(SaoTomeAndPrincipeGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Água Grande')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7);
});

it('maps every Santomean state code to its area', function (): void {
    $mappings = app(SaoTomeAndPrincipeGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(7)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '03', '04', '05', '06', 'P']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SaoTomeAndPrincipeGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Santomean tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('ST');
    $country = AddressCountry::query()->where('iso2', 'ST')->firstOrFail();

    expect($result['seeded'])->toContain('ST')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_region')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);
});
