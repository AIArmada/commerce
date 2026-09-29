<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Mexico\MexicoGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('declares traditional abbreviations for all 32 states', function (): void {
    $names = app(MexicoGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names)->toHaveCount(32)
        ->and($names['mx:state:ciudad-de-mexico'][0])->toBe(['name' => 'CDMX', 'name_type' => 'abbreviation'])
        ->and($names['mx:state:estado-de-mexico'][0]['name'])->toBe('EDOMEX')
        ->and($names['mx:state:quintana-roo'][0]['name'])->toBe('Q. ROO');
});
