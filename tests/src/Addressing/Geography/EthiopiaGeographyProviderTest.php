<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaAddressFormatter;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaGeographyProvider;

it('formats Ethiopian addresses with the postcode left of the locality', function (): void {
    $formatted = app(EthiopiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1519',
        'city' => 'ADDIS ABABA',
        'postcode' => '1000',
        'country_code' => 'ET',
    ]));

    expect($formatted)->toBe("P.O. Box 1519\n1000 ADDIS ABABA\nEthiopia");
});

it('ships 127 zones/woredas under regions with parent links', function (): void {
    $areas = app(EthiopiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['zone', 'woreda']);

    expect($l2)->toHaveCount(127)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('et:zone:gurage')->name)->toBe('Gurage')
        ->and($byId->get('et:woreda:sofi')->name)->toBe('Sofi')
        ->and($byId->get('et:zone:bole')->name)->toBe('Bole');
});

it('labels regions Kilil', function (): void {
    $provider = app(EthiopiaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Kilil'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
