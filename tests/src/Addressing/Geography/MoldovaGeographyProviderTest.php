<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Moldova\MoldovaAddressFormatter;
use AIArmada\Addressing\Geography\Moldova\MoldovaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MoldovaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Moldovan tree with state links', function (): void {
    $this->seedCountry('MD');

    $result = app(SeedCountryGeographiesAction::class)->execute('MD');
    $country = AddressCountry::query()->where('iso2', 'MD')->firstOrFail();

    expect($result['seeded'])->toContain('MD')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(37)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(37)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(32)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_territorial_unit')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'territorial_unit')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(37);
});

it('formats Moldovan addresses with the postcode and comma left of the locality', function (): void {
    $formatted = app(MoldovaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Str. Eminescu, nr. 25/1, ap. 14',
        'city' => 'CHISINAU',
        'postcode' => 'MD-2012',
        'country_code' => 'MD',
    ]));

    expect($formatted)->toBe("Str. Eminescu, nr. 25/1, ap. 14\nMD-2012, CHISINAU\nMoldova");
});
it('formats Moldovan domestic addresses with a bare postcode', function (): void {
    $formatted = app(MoldovaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Str. Eminescu, nr. 25/1, ap. 14',
        'city' => 'CHISINAU',
        'postcode' => '2012',
        'country_code' => 'MD',
    ]));

    expect($formatted)->toBe("Str. Eminescu, nr. 25/1, ap. 14\n2012, CHISINAU\nMoldova");
});
