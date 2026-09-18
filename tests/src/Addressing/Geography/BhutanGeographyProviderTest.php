<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Bhutan\BhutanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 20 Bhutanese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BT')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '33',
        'name' => 'Bumthang (legacy)',
        'label' => 'Bumthang (legacy)',
    ]);

    app(BhutanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Bumthang')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(20);
});

it('maps every Bhutanese state code to its area', function (): void {
    $mappings = app(BhutanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(20)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['33', '12', '22', 'GA', '13', '44', '42', '11', '43', '23', '45', '14', '31', '15', 'TY', '41', '32', '21', '24', '34']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BhutanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Bhutanese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('BT');
    $country = AddressCountry::query()->where('iso2', 'BT')->firstOrFail();

    expect($result['seeded'])->toContain('BT')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(20)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(20);
});
