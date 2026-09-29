<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Cuba\CubaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('names the capital province La Habana with a Havana alias', function (): void {
    $areas = app(CubaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('cu:province:la-habana')->name)->toBe('La Habana')
        ->and($areas->get('cu:province:la-habana')->code)->toBe('03')
        ->and($areas->has('cu:province:havana'))->toBeFalse();

    $names = app(CubaGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['cu:province:la-habana'][0]['name'])->toBe('Havana');
});
