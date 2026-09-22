<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Georgia\GeorgiaAddressFormatter;
use AIArmada\Addressing\Geography\Georgia\GeorgiaGeographyProvider;

it('formats Georgian addresses with the postcode left of the locality', function (): void {
    $formatted = app(GeorgiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Pekinia ave. #39, flat 66',
        'city' => 'TBILISI',
        'postcode' => '0160',
        'country_code' => 'GE',
    ]));

    expect($formatted)->toBe("Pekinia ave. #39, flat 66\n0160 TBILISI\nGeorgia");
});
it('formats rural Georgian addresses with the region below the postcode line', function (): void {
    $formatted = app(GeorgiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Tavisupleba Street 5',
        'city' => 'Telavi',
        'state' => 'Kakheti',
        'postcode' => '2200',
        'country_code' => 'GE',
    ]));

    expect($formatted)->toBe("Tavisupleba Street 5\n2200 Telavi\nKakheti\nGeorgia");
});

it('ships 85 municipalities/districts/cities under regions with parent links', function (): void {
    $areas = app(GeorgiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['municipality', 'district', 'city'])->where('level', 2);

    expect($l2)->toHaveCount(85)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ge:municipality:gori')->name)->toBe('Gori')
        ->and($byId->get('ge:city:batumi')->name)->toBe('Batumi')
        ->and($byId->get('ge:district:gldani')->name)->toBe('Gldani');
});
