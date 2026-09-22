<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Niger\NigerAddressFormatter;
use AIArmada\Addressing\Geography\Niger\NigerGeographyProvider;

it('formats Nigerien addresses with the postcode left of the locality', function (): void {
    $formatted = app(NigerAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 502',
        'city' => 'NIAMEY',
        'postcode' => '8001',
        'country_code' => 'NE',
    ]));

    expect($formatted)->toBe("BP 502\n8001 NIAMEY\nNiger");
});
it('formats Nigerien addresses keeping the abbreviated capital above the region', function (): void {
    $formatted = app(NigerAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 502',
        'city' => 'NY',
        'state' => 'Niamey',
        'postcode' => '8000',
        'country_code' => 'NE',
    ]));

    expect($formatted)->toBe("BP 502\n8000 NY\nNiamey\nNiger");
});

it('ships 71 departments/communes under regions with parent links', function (): void {
    $areas = app(NigerGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['department', 'commune']);

    expect($l2)->toHaveCount(71)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ne:department:arlit')->name)->toBe('Arlit')
        ->and($byId->get('ne:department:dosso')->name)->toBe('Dosso')
        ->and($byId->get('ne:commune:niamey-i')->name)->toBe('Niamey I');
});
