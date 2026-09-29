<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Brazil\BrazilGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('declares UF abbreviations for all 27 states and the federal district', function (): void {
    $names = app(BrazilGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names)->toHaveCount(27)
        ->and($names['br:state:sao-paulo'][0])->toBe(['name' => 'SP', 'name_type' => 'abbreviation'])
        ->and($names['br:federal_district:distrito-federal'][0]['name'])->toBe('DF');
});
