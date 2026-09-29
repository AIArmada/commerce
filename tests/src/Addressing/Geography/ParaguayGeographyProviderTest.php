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
