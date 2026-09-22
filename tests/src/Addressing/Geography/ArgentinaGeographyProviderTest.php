<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Argentina\ArgentinaAddressFormatter;
use AIArmada\Addressing\Geography\Argentina\ArgentinaGeographyProvider;

it('formats Argentine addresses with the CPA postcode left of the locality', function (): void {
    $formatted = app(ArgentinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'TUCUMAN 1560',
        'city' => 'VILLA MARIA',
        'postcode' => 'Y5900FNF',
        'country_code' => 'AR',
    ]));

    expect($formatted)->toBe("TUCUMAN 1560\nY5900FNF VILLA MARIA\nArgentina");
});

it('ships 527 departments/partidos/comunas under provinces with parent links', function (): void {
    $areas = app(ArgentinaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['department', 'partido', 'commune']);

    expect($l2)->toHaveCount(527)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ar:partido:la-matanza')->name)->toBe('La Matanza')
        ->and($byId->get('ar:commune:comuna-1')->name)->toBe('Comuna 1')
        ->and($byId->get('ar:partido:quilmes')->name)->toBe('Quilmes');
});
