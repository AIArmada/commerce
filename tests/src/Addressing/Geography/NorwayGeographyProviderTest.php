<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Norway\NorwayAddressFormatter;
use AIArmada\Addressing\Geography\Norway\NorwayGeographyProvider;

it('formats Norwegian addresses with the postcode left of the locality', function (): void {
    $formatted = app(NorwayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Karl Johansgate 25 B',
        'city' => 'OSLO',
        'postcode' => '0025',
        'country_code' => 'NO',
    ]));

    expect($formatted)->toBe("Karl Johansgate 25 B\n0025 OSLO\nNorway");
});
it('formats Norwegian rural addresses with the village postcode', function (): void {
    $formatted = app(NorwayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ølvevegen 44',
        'city' => 'ØLVE',
        'postcode' => '5637',
        'country_code' => 'NO',
    ]));

    expect($formatted)->toBe("Ølvevegen 44\n5637 ØLVE\nNorway");
});

it('ships 357 municipalitys under countys with parent links', function (): void {
    $areas = app(NorwayGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(357)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('no:municipality:oslo')->name)->toBe('Oslo')
        ->and($byId->get('no:municipality:bergen')->name)->toBe('Bergen')
        ->and($byId->get('no:municipality:trondheim')->name)->toBe('Trondheim');
});
