<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\FrenchGuiana\FrenchGuianaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified French Guiana tree and the accented commune names', function (): void {
    $areas = app(FrenchGuianaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'commune'))->toHaveCount(22);

    // B6 accent fixes (GeoNames + WP titles; ST Lemba precedent):
    // Papaïchton + Rémire-Montjoly. Papaichton's area code is its
    // own postcode 97316 (97340 belongs to Grand-Santi).
    expect($byId->get('gf:commune:papaichton')->name)->toBe('Papaïchton')
        ->and($byId->get('gf:commune:papaichton')->code)->toBe('97316')
        ->and($byId->get('gf:commune:remire-montjoly')->name)->toBe('Rémire-Montjoly')
        ->and($byId->get('gf:commune:grand-santi')->code)->toBe('97340');
});

it('bundles the 25 French Guiana postcodes as single commune primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GF', $dir . '/french-guiana-postal-codes.csv', $dir . '/french-guiana-postal-code-areas.csv', 'aiarmada.addressing.french_guiana');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(25)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // Hexasmal + GeoNames GF, exact. Dual-coded communes: Mana
    // 97318+97360, Roura 97311+97352, Régina 97353+97390.
    expect((string) $byCode->get('97300')->areaSourceId)->toBe('gf:commune:cayenne')
        ->and((string) $byCode->get('97316')->areaSourceId)->toBe('gf:commune:papaichton')
        ->and((string) $byCode->get('97340')->areaSourceId)->toBe('gf:commune:grand-santi')
        ->and((string) $byCode->get('97354')->areaSourceId)->toBe('gf:commune:remire-montjoly')
        ->and((string) $byCode->get('97352')->areaSourceId)->toBe('gf:commune:roura');
});
