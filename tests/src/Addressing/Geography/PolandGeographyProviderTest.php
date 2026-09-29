<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Poland\PolandGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('names voivodeship 16 Opole with an Opolskie alias', function (): void {
    $areas = app(PolandGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $opole = $areas->get('pl:voivodeship:opole');

    expect($opole->name)->toBe('Opole')
        ->and($opole->code)->toBe('16');

    $names = app(PolandGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['pl:voivodeship:opole'][0]['name'])->toBe('Opolskie');
});
