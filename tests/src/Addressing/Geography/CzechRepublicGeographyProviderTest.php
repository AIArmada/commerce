<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 90 Czech states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'CZ')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '201',
        'name' => 'Benešov (legacy)',
        'label' => 'Benešov (legacy)',
    ]);

    app(CzechRepublicGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Benešov')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(90);
});

it('maps every Czech state code to its area', function (): void {
    $mappings = app(CzechRepublicGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(90)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['201', '202', '641', '644', '642', '643', '801', '511', '311', '312', '411', '422', '531', '421', '321', '802', '631', '645', '521', '512', '711', '522', '632', '31', '64', '313', '41', '412', '803', '203', '322', '204', '63', '52', '721', '205', '513', '51', '423', '424', '206', '207', '80', '425', '523', '804', '208', '712', '71', '805', '806', '532', '53', '633', '314', '324', '323', '325', '32', '315', '209', '20A', '10', '714', '20B', '713', '20C', '326', '524', '514', '413', '316', '20', '715', '533', '317', '327', '426', '634', '525', '722', '42', '427', '534', '723', '646', '635', '724', '72', '647']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CzechRepublicGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Czech tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('CZ');
    $country = AddressCountry::query()->where('iso2', 'CZ')->firstOrFail();

    expect($result['seeded'])->toContain('CZ')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(90)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(76)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(90);
});
