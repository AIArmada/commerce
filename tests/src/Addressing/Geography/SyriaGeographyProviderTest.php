<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Syria\SyriaAddressFormatter;
use AIArmada\Addressing\Geography\Syria\SyriaGeographyProvider;

it('formats Syrian addresses without a postcode system', function (): void {
    $formatted = app(SyriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Youssef Al Azamah, no 25',
        'city' => 'DAMASCUS',
        'state' => 'Damascus',
        'country_code' => 'SY',
    ]));

    expect($formatted)->toBe("Rue Youssef Al Azamah, no 25\nDAMASCUS\nSyria");
});
it('prints any supplied Syrian code on its own line', function (): void {
    $formatted = app(SyriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Youssef Al Azamah, no 25',
        'city' => 'DAMASCUS',
        'postcode' => '0100',
        'country_code' => 'SY',
    ]));

    expect($formatted)->toBe("Rue Youssef Al Azamah, no 25\nDAMASCUS\n0100\nSyria");
});

it('ships 66 districts under provinces with parent links', function (): void {
    $areas = app(SyriaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(66)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sy:district:homs')->name)->toBe('Homs')
        ->and($byId->get('sy:district:damascus')->name)->toBe('Damascus')
        ->and($byId->get('sy:district:al-shaddadah')->name)->toBe('Al-Shaddadah');
});
