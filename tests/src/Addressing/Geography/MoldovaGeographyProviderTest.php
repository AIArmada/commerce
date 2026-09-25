<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Moldova\MoldovaAddressFormatter;
use AIArmada\Addressing\Geography\Moldova\MoldovaGeographyProvider;

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

it('ships 981 communes and cities under districts with parent links', function (): void {
    $areas = app(MoldovaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(981)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'city'))->toHaveCount(66)
        ->and($l2->where('type', 'commune'))->toHaveCount(915)
        ->and($l2->where('parentSourceId', 'md:territorial_unit:transnistria'))->toHaveCount(79)
        ->and($byId->get('md:city:donduseni')->name)->toBe('Dondușeni (city)')
        ->and($byId->get('md:commune:donduseni')->parentSourceId)->toBe('md:district:donduseni');
});

it('labels tiers Raion, Comună and Oraș', function (): void {
    $provider = app(MoldovaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['district' => 'Raion', 'commune' => 'Comună', 'city' => 'Oraș'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
