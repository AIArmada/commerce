<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Philippines\PhilippinesAddressFormatter;
use AIArmada\Addressing\Geography\Philippines\PhilippinesGeographyProvider;

it('formats Filipino addresses with the postcode left of the locality', function (): void {
    $formatted = app(PhilippinesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rm 602 FUBC Bldg, Escolta',
        'city' => 'MANILA',
        'postcode' => '1008',
        'country_code' => 'PH',
    ]));

    expect($formatted)->toBe("Rm 602 FUBC Bldg, Escolta\n1008 MANILA\nPhilippines");
});
it('formats Filipino provincial addresses with the postcode on the province line', function (): void {
    $formatted = app(PhilippinesAddressFormatter::class)->format(AddressData::from([
        'line1' => '96 Hermogenes St., Sofa Subdivision',
        'city' => 'San Fernando',
        'state' => 'PAMPANGA',
        'postcode' => '2000',
        'country_code' => 'PH',
    ]));

    expect($formatted)->toBe("96 Hermogenes St., Sofa Subdivision\nSan Fernando\n2000 PAMPANGA\nPhilippines");
});
it('formats Filipino provincial addresses with province only', function (): void {
    $formatted = app(PhilippinesAddressFormatter::class)->format(AddressData::from([
        'line1' => '96 Hermogenes St., Sofa Subdivision',
        'state' => 'PAMPANGA',
        'postcode' => '2000',
        'country_code' => 'PH',
    ]));

    expect($formatted)->toBe("96 Hermogenes St., Sofa Subdivision\n2000 PAMPANGA\nPhilippines");
});
it('formats Filipino Metro Manila addresses on a single postcode line', function (): void {
    $formatted = app(PhilippinesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1121, Araneta Center P.O.',
        'city' => 'Quezon City',
        'state' => 'METRO MANILA',
        'postcode' => '1135',
        'country_code' => 'PH',
    ]));

    expect($formatted)->toBe("P.O. Box 1121, Araneta Center P.O.\n1135 Quezon City, METRO MANILA\nPhilippines");
});
it('prints Filipino city-states once when city and state match', function (): void {
    $formatted = app(PhilippinesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rm 602 FUBC Bldg, Escolta',
        'city' => 'manila',
        'state' => 'MANILA',
        'postcode' => '1008',
        'country_code' => 'PH',
    ]));

    expect($formatted)->toBe("Rm 602 FUBC Bldg, Escolta\n1008 MANILA\nPhilippines");
});

it('ships 1656 municipalities and cities under provinces plus NCR', function (): void {
    $areas = app(PhilippinesGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(1656)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'city'))->toHaveCount(149)
        ->and($l2->where('type', 'municipality'))->toHaveCount(1493)
        ->and($l2->where('type', 'sub_municipality'))->toHaveCount(14)
        ->and($byId->get('ph:sub_municipality:tondo-i-ii')->parentSourceId)->toBe('ph:region:national-capital-region');
});

it('ships 42011 barangays under their municipalities', function (): void {
    $areas = app(PhilippinesGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l3 = $areas->where('level', 3);

    expect($l3)->toHaveCount(42011)
        ->and($l3->pluck('parentSourceId')->every(fn ($p) => $byId->has($p) && $byId->get($p)->level === 2))->toBeTrue()
        ->and($byId->get('ph:barangay:50th-district')->parentSourceId)->toBe('ph:city:city-of-ozamiz');
});
