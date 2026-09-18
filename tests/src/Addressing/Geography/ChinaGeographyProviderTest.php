<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\China\ChinaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 33 Chinese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'CN')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BJ',
        'name' => 'Peking',
        'label' => 'Peking',
    ]);

    app(ChinaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Beijing')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(33);
});

it('maps every Chinese state code to its area', function (): void {
    $mappings = app(ChinaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(33)
        ->and(array_keys($mappings))->toBe(['AH', 'BJ', 'CQ', 'FJ', 'GD', 'GS', 'GX', 'GZ', 'HA', 'HB', 'HE', 'HI', 'HK', 'HL', 'HN', 'JL', 'JS', 'JX', 'LN', 'MO', 'NM', 'NX', 'QH', 'SC', 'SD', 'SH', 'SN', 'SX', 'TJ', 'XJ', 'XZ', 'YN', 'ZJ']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ChinaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Chinese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('CN');
    $country = AddressCountry::query()->where('iso2', 'CN')->firstOrFail();

    expect($result['seeded'])->toContain('CN')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(33)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_region')->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_administrative_region')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(33);
});
