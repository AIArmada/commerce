<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\TrinidadAndTobago\TrinidadAndTobagoAddressFormatter;
use AIArmada\Addressing\Geography\TrinidadAndTobago\TrinidadAndTobagoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TrinidadAndTobagoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Trinidadian and Tobagonian tree with state links', function (): void {
    $this->seedCountry('TT');

    $result = app(SeedCountryGeographiesAction::class)->execute('TT');
    $country = AddressCountry::query()->where('iso2', 'TT')->firstOrFail();

    expect($result['seeded'])->toContain('TT')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'borough')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'ward')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(17);
});

it('formats Trinidadian addresses with the postcode right of the locality', function (): void {
    $formatted = app(TrinidadAndTobagoAddressFormatter::class)->format(AddressData::from([
        'line1' => '135-137 Southern Main Road',
        'city' => 'CHAGUANAS',
        'postcode' => '500234',
        'country_code' => 'TT',
    ]));

    expect($formatted)->toBe("135-137 Southern Main Road\nCHAGUANAS 500234\nTrinidad and Tobago");
});
it('formats Port-of-Spain addresses with the box postcode', function (): void {
    $formatted = app(TrinidadAndTobagoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1872',
        'city' => 'PORT-OF-SPAIN',
        'postcode' => '150123',
        'country_code' => 'TT',
    ]));

    expect($formatted)->toBe("PO Box 1872\nPORT-OF-SPAIN 150123\nTrinidad and Tobago");
});
