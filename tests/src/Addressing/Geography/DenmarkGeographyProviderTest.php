<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Denmark\DenmarkGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('names region 84 Capital Region with a Hovedstaden alias', function (): void {
    $areas = app(DenmarkGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $capital = $areas->get('dk:region:capital-region');

    expect($capital->name)->toBe('Capital Region')
        ->and($capital->code)->toBe('84');

    $names = app(DenmarkGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['dk:region:capital-region'][0]['name'])->toBe('Hovedstaden');
});
