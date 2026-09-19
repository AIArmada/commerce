<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\NorthKorea\NorthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\NorthKorea\NorthKoreaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NorthKoreaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the North Korean tree with state links', function (): void {
    $this->seedCountry('KP');

    $result = app(SeedCountryGeographiesAction::class)->execute('KP');
    $country = AddressCountry::query()->where('iso2', 'KP')->firstOrFail();

    expect($result['seeded'])->toContain('KP')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'metropolitan_city')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_city')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(13);
});

it('formats North Korean addresses without a postcode system', function (): void {
    $formatted = app(NorthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Quartier Bottongang',
        'city' => 'PYONGYANG',
        'country_code' => 'KP',
    ]));

    expect($formatted)->toBe("Quartier Bottongang\nPYONGYANG\nNorth Korea");
});
it('prints any supplied North Korean code on its own line', function (): void {
    $formatted = app(NorthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Quartier Bottongang',
        'city' => 'PYONGYANG',
        'postcode' => '999999',
        'country_code' => 'KP',
    ]));

    expect($formatted)->toBe("Quartier Bottongang\nPYONGYANG\n999999\nNorth Korea");
});
