<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Poland\PolandAddressFormatter;
use AIArmada\Addressing\Geography\Poland\PolandGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Polish addresses with the dashed postcode left of the locality', function (): void {
    $formatted = app(PolandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ul. Kręta 15m 10',
        'city' => 'WARSZAWA',
        'postcode' => '00-950',
        'country_code' => 'PL',
    ]));

    expect($formatted)->toBe("Ul. Kręta 15m 10\n00-950 WARSZAWA\nPoland");
});

it('names voivodeship 16 Opole with an Opolskie alias', function (): void {
    $areas = app(PolandGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $opole = $areas->get('pl:voivodeship:opole');

    expect($opole->name)->toBe('Opole')
        ->and($opole->code)->toBe('16');

    $names = app(PolandGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['pl:voivodeship:opole'][0]['name'])->toBe('Opolskie');
});

it('ships 380 land/city counties under voivodeships with parent links', function (): void {
    $areas = app(PolandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['land_county', 'city_county']);

    expect($l2)->toHaveCount(380)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('pl:city_county:warszawa')->name)->toBe('Warszawa')
        ->and($byId->get('pl:city_county:krakow')->name)->toBe('Kraków')
        ->and($byId->get('pl:land_county:powiat-krakowski')->name)->toBe('powiat krakowski');
});
