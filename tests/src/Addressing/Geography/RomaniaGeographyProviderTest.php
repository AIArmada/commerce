<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Romania\RomaniaAddressFormatter;
use AIArmada\Addressing\Geography\Romania\RomaniaGeographyProvider;

it('formats Romanian addresses with the postcode left of the locality', function (): void {
    $formatted = app(RomaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Drumul Taberei nr. 35, bl. F5, sc. 2, parter, ap. 23',
        'line2' => 'Sector 6',
        'city' => 'BUCHAREST',
        'postcode' => '061357',
        'country_code' => 'RO',
    ]));

    expect($formatted)->toBe("Drumul Taberei nr. 35, bl. F5, sc. 2, parter, ap. 23\nSector 6\n061357 BUCHAREST\nRomania");
});
it('formats Romanian addresses with the county below the postcode line', function (): void {
    $formatted = app(RomaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Strada Republicii 10',
        'city' => 'Craiova',
        'state' => 'Dolj',
        'postcode' => '200716',
        'country_code' => 'RO',
    ]));

    expect($formatted)->toBe("Strada Republicii 10\n200716 Craiova\nDolj\nRomania");
});
it('exposes corrected Romanian department names', function (): void {
    $areas = app(RomaniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ro:department:arges')->name)->toBe('Argeș')
        ->and($areas->get('ro:department:arges')->code)->toBe('AG')
        ->and($areas->get('ro:department:arges')->type)->toBe('department')
        ->and($areas->get('ro:department:braila')->name)->toBe('Brăila')
        ->and($areas->get('ro:department:braila')->code)->toBe('BR');
});

it('ships 3186 communes, towns, municipalities and sectors under counties with parent links', function (): void {
    $areas = app(RomaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(3186)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'commune'))->toHaveCount(2861)
        ->and($l2->where('type', 'town'))->toHaveCount(217)
        ->and($l2->where('type', 'municipality'))->toHaveCount(102)
        ->and($l2->where('type', 'sector'))->toHaveCount(6)
        ->and($byId->get('ro:town:baneasa')->parentSourceId)->toBe('ro:department:constanta')
        ->and($byId->get('ro:sector:sector-1')->code)->toBe('S1');
});
