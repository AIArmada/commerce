<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Hungary\HungaryGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 43 Hungarian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'HU')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BK',
        'name' => 'Bács-Kiskun (legacy)',
        'label' => 'Bács-Kiskun (legacy)',
    ]);

    app(HungaryGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Bács-Kiskun')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(43);
});

it('maps every Hungarian state code to its area', function (): void {
    $mappings = app(HungaryGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(43)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BK', 'BA', 'BE', 'BC', 'BZ', 'BU', 'CS', 'DE', 'DU', 'EG', 'ER', 'FE', 'GY', 'GS', 'HB', 'HE', 'HV', 'JN', 'KV', 'KM', 'KE', 'MI', 'NK', 'NO', 'NY', 'PS', 'PE', 'ST', 'SO', 'SN', 'SZ', 'SD', 'SF', 'SS', 'SK', 'SH', 'TB', 'TO', 'VA', 'VM', 'VE', 'ZA', 'ZE']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(HungaryGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Hungarian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('HU');
    $country = AddressCountry::query()->where('iso2', 'HU')->firstOrFail();

    expect($result['seeded'])->toContain('HU')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(43)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city_with_county_rights')->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(43);
});
