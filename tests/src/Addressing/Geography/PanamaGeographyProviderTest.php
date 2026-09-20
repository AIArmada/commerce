<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Panama\PanamaAddressFormatter;

it('formats Panamanian addresses without a postcode system', function (): void {
    $formatted = app(PanamaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'VIA ESPAÑA, CALLE 6ta',
        'line2' => 'EDIFICIO DEL PADRO No2, TERCER PISO, LOCAL No10, PARQUE LEFEVRE',
        'city' => 'PARQUE LEFEVRE',
        'state' => 'PROVINCIA DE PANAMÁ',
        'country_code' => 'PA',
    ]));

    expect($formatted)->toBe("VIA ESPAÑA, CALLE 6ta\nEDIFICIO DEL PADRO No2, TERCER PISO, LOCAL No10, PARQUE LEFEVRE\nPARQUE LEFEVRE\nPROVINCIA DE PANAMÁ\nPanama");
});
it('formats Panamanian box addresses keeping the zone box in the street line', function (): void {
    $formatted = app(PanamaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'APARTADO POSTAL 0832-02345',
        'city' => 'PARQUE LEFEVRE',
        'state' => 'PROVINCIA DE PANAMÁ',
        'country_code' => 'PA',
    ]));

    expect($formatted)->toBe("APARTADO POSTAL 0832-02345\nPARQUE LEFEVRE\nPROVINCIA DE PANAMÁ\nPanama");
});
