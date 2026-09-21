<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaAddressFormatter;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaGeographyProvider;

it('formats Lithuanian inbound addresses with the LT postcode prefix', function (): void {
    $formatted = app(LithuaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Laisvės pr. 40-12',
        'city' => 'Vilnius',
        'postcode' => 'LT-04340',
        'country_code' => 'LT',
    ]));

    expect($formatted)->toBe("Laisvės pr. 40-12\nLT-04340 Vilnius\nLithuania");
});
it('formats Lithuanian domestic addresses with a bare postcode', function (): void {
    $formatted = app(LithuaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Laisvės al. 60',
        'city' => 'Kaunas',
        'postcode' => '44280',
        'country_code' => 'LT',
    ]));

    expect($formatted)->toBe("Laisvės al. 60\n44280 Kaunas\nLithuania");
});

it('types the seven city municipalities with miestas names', function (): void {
    $areas = app(LithuaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $cities = $areas->where('type', 'city_municipality')->keyBy->code;

    expect($cities)->toHaveCount(7)
        ->and($cities->get('02')->name)->toBe('Alytaus miestas')
        ->and($cities->get('15')->name)->toBe('Kauno miestas')
        ->and($cities->get('20')->name)->toBe('Klaipėdos miestas')
        ->and($cities->get('31')->name)->toBe('Palangos miestas')
        ->and($cities->get('32')->name)->toBe('Panevėžio miestas')
        ->and($cities->get('43')->name)->toBe('Šiaulių miestas')
        ->and($cities->get('57')->name)->toBe('Vilniaus miestas');

    $byId = $areas->keyBy->sourceId;

    expect($byId->get('lt:district_municipality:klaipeda')->code)->toBe('21')
        ->and($byId->get('lt:district_municipality:panevezys')->code)->toBe('33');
});
