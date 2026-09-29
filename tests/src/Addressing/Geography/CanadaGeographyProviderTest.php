<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Canada\CanadaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('declares postal abbreviations for all 13 provinces and territories', function (): void {
    $names = app(CanadaGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names)->toHaveCount(13)
        ->and($names['ca:province:ontario'][0])->toBe(['name' => 'ON', 'name_type' => 'abbreviation'])
        ->and($names['ca:province:quebec'][0]['name'])->toBe('QC')
        ->and($names['ca:province:quebec'][1])->toBe(['name' => 'Québec', 'name_type' => 'alternative'])
        ->and($names['ca:territory:nunavut'][0]['name'])->toBe('NU');
});
