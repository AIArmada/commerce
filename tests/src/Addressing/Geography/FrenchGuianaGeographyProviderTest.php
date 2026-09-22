<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\FrenchGuiana\FrenchGuianaAddressFormatter;
use AIArmada\Addressing\Geography\FrenchGuiana\FrenchGuianaGeographyProvider;

it('formats Guianese addresses with the postcode left of the locality', function (): void {
    $formatted = app(FrenchGuianaAddressFormatter::class)->format(AddressData::from([
        'line1' => '3 AVENUE HENRI AGARANDE',
        'city' => 'CAYENNE',
        'postcode' => '97300',
        'country_code' => 'GF',
    ]));

    expect($formatted)->toBe("3 AVENUE HENRI AGARANDE\n97300 CAYENNE\nFrench Guiana");
});
it('formats Guianese Kourou addresses with the town postcode', function (): void {
    $formatted = app(FrenchGuianaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenue des Roches 1',
        'city' => 'KOUROU',
        'postcode' => '97310',
        'country_code' => 'GF',
    ]));

    expect($formatted)->toBe("Avenue des Roches 1\n97310 KOUROU\nFrench Guiana");
});

it('ships 22 communes under overseas_regions with parent links', function (): void {
    $areas = app(FrenchGuianaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'commune');

    expect($l2)->toHaveCount(22)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('gf:commune:cayenne')->name)->toBe('Cayenne')
        ->and($byId->get('gf:commune:kourou')->name)->toBe('Kourou')
        ->and($byId->get('gf:commune:saint-laurent-du-maroni')->name)->toBe('Saint-Laurent-du-Maroni');
});
