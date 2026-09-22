<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\France\FranceAddressFormatter;
use AIArmada\Addressing\Geography\France\FranceGeographyProvider;

it('formats French addresses with the postcode left of the locality', function (): void {
    $formatted = app(FranceAddressFormatter::class)->format(AddressData::from([
        'line1' => '25 RUE DES FLEURS',
        'city' => 'LIBOURNE',
        'postcode' => '33500',
        'country_code' => 'FR',
    ]));

    expect($formatted)->toBe("25 RUE DES FLEURS\n33500 LIBOURNE\nFrance");
});

it('ships 102 departments under regions with parent links', function (): void {
    $areas = app(FranceGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'department');

    expect($l2)->toHaveCount(102)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('fr:department:paris')->name)->toBe('Paris')
        ->and($byId->get('fr:department:nord')->name)->toBe('Nord')
        ->and($byId->get('fr:department:bouches-du-rhone')->name)->toBe('Bouches-du-Rhône');
});
