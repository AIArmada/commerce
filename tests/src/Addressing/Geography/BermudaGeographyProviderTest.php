<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bermuda\BermudaAddressFormatter;
use AIArmada\Addressing\Geography\Bermuda\BermudaGeographyProvider;

it('formats Bermudian street addresses with the postcode right of the parish', function (): void {
    $formatted = app(BermudaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Upper Apt # 1',
        'line2' => '9 Leafy Lane',
        'state' => "SMITH'S",
        'postcode' => 'FL 07',
        'country_code' => 'BM',
    ]));

    expect($formatted)->toBe("Upper Apt # 1\n9 Leafy Lane\nSMITH'S FL 07\nBermuda");
});
it('formats Bermudian box addresses with the letter postcode', function (): void {
    $formatted = app(BermudaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box HM 2469',
        'city' => 'HAMILTON',
        'postcode' => 'HM GX',
        'country_code' => 'BM',
    ]));

    expect($formatted)->toBe("PO Box HM 2469\nHAMILTON HM GX\nBermuda");
});

it('ships 9 parishes plus the two municipalities under their parishes', function (): void {
    $areas = app(BermudaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(9)
        ->and($areas->where('type', 'municipality'))->toHaveCount(2)
        ->and($byId->get('bm:municipality:hamilton')->parentSourceId)->toBe('bm:parish:pembroke')
        ->and($byId->get('bm:municipality:saint-georges')->parentSourceId)->toBe('bm:parish:saint-georges');
});
