<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicAddressFormatter;
use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicGeographyProvider;

it('formats Central African addresses without a postcode system', function (): void {
    $formatted = app(CentralAfricanRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 729',
        'city' => 'BANGUI',
        'country_code' => 'CF',
    ]));

    expect($formatted)->toBe("BP 729\nBANGUI\nCentral African Republic");
});
it('prints any supplied Central African code on its own line', function (): void {
    $formatted = app(CentralAfricanRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 729',
        'city' => 'BANGUI',
        'postcode' => '99999',
        'country_code' => 'CF',
    ]));

    expect($formatted)->toBe("BP 729\nBANGUI\n99999\nCentral African Republic");
});

it('ships 20 prefectures with Bangui and Sangha-Mbaéré retyped', function (): void {
    $areas = app(CentralAfricanRepublicGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 1))->toHaveCount(20);

    $byId = $areas->keyBy('sourceId');

    expect($byId->has('cf:commune:bangui'))->toBeFalse()
        ->and($byId->get('cf:prefecture:bangui')->type)->toBe('prefecture')
        ->and($byId->has('cf:prefecture:sangha-mbaere'))->toBeFalse()
        ->and($byId->get('cf:economic_prefecture:sangha-mbaere')->type)->toBe('economic_prefecture')
        ->and($byId->get('cf:prefecture:lim-pende')->name)->toBe('Lim-Pendé')
        ->and($byId->get('cf:prefecture:mambere')->name)->toBe('Mambéré')
        ->and($byId->get('cf:prefecture:ouham-fafa')->name)->toBe('Ouham-Fafa');
});

it('ships 80 subprefectures under prefectures with parent links', function (): void {
    $areas = app(CentralAfricanRepublicGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'subprefecture');

    expect($l2)->toHaveCount(80)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('cf:subprefecture:bamingui')->name)->toBe('Bamingui')
        ->and($byId->get('cf:subprefecture:alindao')->name)->toBe('Alindao')
        ->and($byId->get('cf:subprefecture:ndele')->name)->toBe('Ndélé');
});
