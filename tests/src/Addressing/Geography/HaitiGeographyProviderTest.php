<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Haiti\HaitiAddressFormatter;
use AIArmada\Addressing\Geography\Haiti\HaitiGeographyProvider;

it('formats Haitian addresses with the HT postcode left of the locality', function (): void {
    $formatted = app(HaitiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Samba 1',
        'city' => 'DELMAS',
        'postcode' => 'HT6120',
        'country_code' => 'HT',
    ]));

    expect($formatted)->toBe("Rue Samba 1\nHT6120 DELMAS\nHaiti");
});
it('formats Haitian capital addresses with the Port-au-Prince postcode', function (): void {
    $formatted = app(HaitiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Capois 5',
        'city' => 'PORT-AU-PRINCE',
        'postcode' => 'HT6110',
        'country_code' => 'HT',
    ]));

    expect($formatted)->toBe("Rue Capois 5\nHT6110 PORT-AU-PRINCE\nHaiti");
});

it('ships 42 arrondissements under departments with parent links', function (): void {
    $areas = app(HaitiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'arrondissement');

    expect($l2)->toHaveCount(42)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ht:arrondissement:cap-haitien')->name)->toBe('Cap-Haïtien')
        ->and($byId->get('ht:arrondissement:port-au-prince')->name)->toBe('Port-au-Prince')
        ->and($byId->get('ht:arrondissement:la-gonave')->name)->toBe('La Gonâve');
});
