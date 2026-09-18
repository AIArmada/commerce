<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Armenia\ArmeniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 11 Armenian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'AM')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'AG',
        'name' => 'Aragatsotn (legacy)',
        'label' => 'Aragatsotn (legacy)',
    ]);

    app(ArmeniaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Aragatsotn')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(11);
});

it('maps every Armenian state code to its area', function (): void {
    $mappings = app(ArmeniaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(11)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['AG', 'AR', 'AV', 'GR', 'KT', 'LO', 'SH', 'SU', 'TV', 'VD', 'ER']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ArmeniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Armenian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('AM');
    $country = AddressCountry::query()->where('iso2', 'AM')->firstOrFail();

    expect($result['seeded'])->toContain('AM')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(11);
});
