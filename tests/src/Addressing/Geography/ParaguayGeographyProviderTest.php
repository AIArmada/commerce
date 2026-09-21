<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Paraguay\ParaguayAddressFormatter;
use AIArmada\Addressing\Geography\Paraguay\ParaguayGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

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

it('types Asunción as a capital district with a department role', function (): void {
    $areas = app(ParaguayGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('py:capital_district:asuncion')->name)->toBe('Asunción')
        ->and($areas->get('py:capital_district:asuncion')->code)->toBe('ASU')
        ->and($areas->has('py:department:asuncion'))->toBeFalse();

    $roles = app(ParaguayGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['py:capital_district:asuncion'][0]['role'])->toBe('department');
});
