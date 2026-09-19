<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bolivia\BoliviaAddressFormatter;
use AIArmada\Addressing\Geography\Bolivia\BoliviaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BoliviaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Bolivian tree with state links', function (): void {
    $this->seedCountry('BO');

    $result = app(SeedCountryGeographiesAction::class)->execute('BO');
    $country = AddressCountry::query()->where('iso2', 'BO')->firstOrFail();

    expect($result['seeded'])->toContain('BO')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(9)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});

it('formats Bolivian addresses without a postcode system', function (): void {
    $formatted = app(BoliviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'CALLE AZURDUY 158',
        'city' => 'SUCRE',
        'country_code' => 'BO',
    ]));

    expect($formatted)->toBe("CALLE AZURDUY 158\nSUCRE\nBolivia");
});
it('formats Bolivian box addresses with the casilla in the street line', function (): void {
    $formatted = app(BoliviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Casilla Postal 1234',
        'city' => 'LA PAZ',
        'country_code' => 'BO',
    ]));

    expect($formatted)->toBe("Casilla Postal 1234\nLA PAZ\nBolivia");
});
