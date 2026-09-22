<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Hungary\HungaryAddressFormatter;
use AIArmada\Addressing\Geography\Hungary\HungaryGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Hungarian addresses with the postcode and town on one line', function (): void {
    $formatted = app(HungaryAddressFormatter::class)->format(AddressData::from([
        'line1' => 'VIRÁG TÉR 3. IV. 61',
        'city' => 'BUDAPEST',
        'postcode' => '1037',
        'country_code' => 'HU',
    ]));

    expect($formatted)->toBe("VIRÁG TÉR 3. IV. 61\n1037 BUDAPEST\nHungary");
});
it('formats Hungarian post box addresses with the box postcode', function (): void {
    $formatted = app(HungaryAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PF. 83',
        'city' => 'DABAS',
        'postcode' => '2380',
        'country_code' => 'HU',
    ]));

    expect($formatted)->toBe("PF. 83\n2380 DABAS\nHungary");
});

it('renames Csongrád County and types Zalaegerszeg as a city', function (): void {
    $areas = app(HungaryGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('hu:county:csongrad-csanad-county')->name)->toBe('Csongrád-Csanád County')
        ->and($areas->get('hu:city_with_county_rights:zalaegerszeg')->code)->toBe('ZE');

    $names = app(HungaryGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['hu:county:csongrad-csanad-county'][0]['name'])->toBe('Csongrád County');
});

it('ships 197 districts under countys with parent links', function (): void {
    $areas = app(HungaryGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(197)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('hu:district:kispest')->name)->toBe('Kispest')
        ->and($byId->get('hu:district:varkerulet')->name)->toBe('Várkerület')
        ->and($byId->get('hu:district:debrecen')->name)->toBe('Debrecen');
});
