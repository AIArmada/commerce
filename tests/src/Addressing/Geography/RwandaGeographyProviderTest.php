<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Rwanda\RwandaAddressFormatter;
use AIArmada\Addressing\Geography\Rwanda\RwandaGeographyProvider;

it('formats Rwandan addresses without a postcode system', function (): void {
    $formatted = app(RwandaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 3425',
        'city' => 'KIGALI',
        'country_code' => 'RW',
    ]));

    expect($formatted)->toBe("B.P. 3425\nKIGALI\nRwanda");
});
it('formats Rwandan addresses with the province below the locality', function (): void {
    $formatted = app(RwandaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'KN 3 Road',
        'city' => 'Butare',
        'state' => 'Southern',
        'country_code' => 'RW',
    ]));

    expect($formatted)->toBe("KN 3 Road\nButare\nSouthern\nRwanda");
});

it('ships 30 districts under provinces with parent links', function (): void {
    $areas = app(RwandaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(30)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('rw:district:gasabo')->name)->toBe('Gasabo')
        ->and($byId->get('rw:district:nyarugenge')->name)->toBe('Nyarugenge')
        ->and($byId->get('rw:district:karongi')->name)->toBe('Karongi');
});
