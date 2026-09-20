<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Paraguay\ParaguayAddressFormatter;

it('formats Paraguayan addresses with the postcode left of the locality', function (): void {
    $formatted = app(ParaguayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Estrella Nº 340, casi Yegros',
        'line2' => 'Edif. España, Bloque A, Piso 3, Depto. 10',
        'city' => 'ASUNCIÓN',
        'state' => 'CENTRAL',
        'postcode' => '001218',
        'country_code' => 'PY',
    ]));

    expect($formatted)->toBe("Estrella Nº 340, casi Yegros\nEdif. España, Bloque A, Piso 3, Depto. 10\n001218 ASUNCIÓN\nCENTRAL\nParaguay");
});
it('formats Paraguayan rural addresses with the town postcode', function (): void {
    $formatted = app(ParaguayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ruta 1 km 45',
        'city' => 'ALBERDI',
        'state' => 'ÑEEMBUCU',
        'postcode' => '120203',
        'country_code' => 'PY',
    ]));

    expect($formatted)->toBe("Ruta 1 km 45\n120203 ALBERDI\nÑEEMBUCU\nParaguay");
});
