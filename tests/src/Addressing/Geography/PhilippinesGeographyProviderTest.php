<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Philippines\PhilippinesAddressFormatter;

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
