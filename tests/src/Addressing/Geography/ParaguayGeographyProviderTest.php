<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Paraguay\ParaguayGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('types Asunción as a capital district with a department role', function (): void {
    $areas = app(ParaguayGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('py:capital_district:asuncion')->name)->toBe('Asunción')
        ->and($areas->get('py:capital_district:asuncion')->code)->toBe('ASU')
        ->and($areas->has('py:department:asuncion'))->toBeFalse();

    $roles = app(ParaguayGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['py:capital_district:asuncion'][0]['role'])->toBe('department');
});

it('ships 263 districts with the B17-verified spelling bridges', function (): void {
    $areas = app(ParaguayGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(281)
        ->and($areas->where('level', '2'))->toHaveCount(263)
        ->and($byId->get('py:district:guayaibi')->name)->toBe('Guayaibí')
        ->and($byId->get('py:district:doctor-botrell')->name)->toBe('Doctor Botrell')
        ->and($byId->get('py:district:san-pedro-de-ycuamandiyu')->name)->toBe('San Pedro de Ycuamandiyú')
        ->and($byId->get('py:district:nueva-asuncion')->parentSourceId)->toBe('py:department:presidente-hayes');
});
