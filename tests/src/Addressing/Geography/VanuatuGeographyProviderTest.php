<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Vanuatu\VanuatuAddressFormatter;
use AIArmada\Addressing\Geography\Vanuatu\VanuatuGeographyProvider;

it('formats Vanuatu addresses without a postcode system', function (): void {
    $formatted = app(VanuatuAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Port Vila',
        'country_code' => 'VU',
    ]));

    expect($formatted)->toBe("PO Box 1\nPort Vila\nVanuatu");
});

it('prints any supplied Port Vila code on its own line', function (): void {
    $formatted = app(VanuatuAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Port Vila',
        'postcode' => '99999',
        'country_code' => 'VU',
    ]));

    expect($formatted)->toBe("PO Box 1\nPort Vila\n99999\nVanuatu");
});

it('ships 60 area councils and 3 municipalities with parent links', function (): void {
    $areas = app(VanuatuGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(63)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('type', 'municipality'))->toHaveCount(3)
        ->and($byId->get('vu:municipality:port-vila')->code)->toBe('VU.SE.PV')
        ->and($byId->get('vu:municipality:lenakel')->name)->toBe('Lenakel')
        ->and($byId->get('vu:area_council:central-pentecost-1')->name)->toBe('Central Pentecost 1');
});
