<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Sweden\SwedenAddressFormatter;
use AIArmada\Addressing\Geography\Sweden\SwedenGeographyProvider;

it('formats Swedish addresses with the spaced postcode left of the locality', function (): void {
    $formatted = app(SwedenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'NYBY 10',
        'city' => 'LILLBYN',
        'postcode' => '123 45',
        'country_code' => 'SE',
    ]));

    expect($formatted)->toBe("NYBY 10\n123 45 LILLBYN\nSweden");
});
it('formats Swedish box addresses with the box postcode', function (): void {
    $formatted = app(SwedenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BOX 222',
        'city' => 'STOCKHOLM',
        'postcode' => '111 81',
        'country_code' => 'SE',
    ]));

    expect($formatted)->toBe("BOX 222\n111 81 STOCKHOLM\nSweden");
});

it('ships 290 municipalitys under countys with parent links', function (): void {
    $areas = app(SwedenGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(290)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('se:municipality:stockholm')->name)->toBe('Stockholm')
        ->and($byId->get('se:municipality:gothenburg')->name)->toBe('Gothenburg')
        ->and($byId->get('se:municipality:malmo')->name)->toBe('Malmö');
});
