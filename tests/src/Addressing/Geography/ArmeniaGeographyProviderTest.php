<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Armenia\ArmeniaAddressFormatter;
use AIArmada\Addressing\Geography\Armenia\ArmeniaGeographyProvider;

it('formats Armenian addresses with the postcode left of the locality', function (): void {
    $formatted = app(ArmeniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Saryan str 22 apt 25',
        'city' => 'YEREVAN',
        'postcode' => '0002',
        'country_code' => 'AM',
    ]));

    expect($formatted)->toBe("Saryan str 22 apt 25\n0002 YEREVAN\nArmenia");
});
it('formats rural Armenian addresses with the region below the postcode line', function (): void {
    $formatted = app(ArmeniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Mashtots Street 1',
        'city' => 'Vedi',
        'state' => 'Ararat',
        'postcode' => '0601',
        'country_code' => 'AM',
    ]));

    expect($formatted)->toBe("Mashtots Street 1\n0601 Vedi\nArarat\nArmenia");
});

it('ships 81 municipalities/districts under regions with parent links', function (): void {
    $areas = app(ArmeniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['municipality', 'district']);

    expect($l2)->toHaveCount(81)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('am:municipality:gyumri')->name)->toBe('Gyumri')
        ->and($byId->get('am:municipality:vanadzor')->name)->toBe('Vanadzor')
        ->and($byId->get('am:district:kentron')->name)->toBe('Kentron');
});
