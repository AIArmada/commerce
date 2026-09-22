<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Germany\GermanyAddressFormatter;
use AIArmada\Addressing\Geography\Germany\GermanyGeographyProvider;

it('formats German addresses with the postcode left of the locality', function (): void {
    $formatted = app(GermanyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Wacholderweg 52a',
        'city' => 'OLDENBURG',
        'postcode' => '26133',
        'country_code' => 'DE',
    ]));

    expect($formatted)->toBe("Wacholderweg 52a\n26133 OLDENBURG\nGermany");
});

it('ships 401 districts under states with parent links', function (): void {
    $areas = app(GermanyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(401)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('de:district:berlin')->name)->toBe('Berlin')
        ->and($byId->get('de:district:hamburg')->name)->toBe('Hamburg')
        ->and($byId->get('de:district:munich')->name)->toBe('Munich');
});
