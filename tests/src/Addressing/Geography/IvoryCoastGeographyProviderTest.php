<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastAddressFormatter;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastGeographyProvider;

it('formats Ivorian addresses without a postcode system', function (): void {
    $formatted = app(IvoryCoastAddressFormatter::class)->format(AddressData::from([
        'line1' => '06 B.P. 37',
        'city' => 'ABIDJAN',
        'country_code' => 'CI',
    ]));

    expect($formatted)->toBe("06 B.P. 37\nABIDJAN\nIvory Coast");
});
it('prints any supplied Ivorian code on its own line', function (): void {
    $formatted = app(IvoryCoastAddressFormatter::class)->format(AddressData::from([
        'line1' => '06 B.P. 37',
        'city' => 'ABIDJAN',
        'postcode' => '99999',
        'country_code' => 'CI',
    ]));

    expect($formatted)->toBe("06 B.P. 37\nABIDJAN\n99999\nIvory Coast");
});

it('ships 31 regions under districts with parent links', function (): void {
    $areas = app(IvoryCoastGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'region');

    expect($l2)->toHaveCount(31)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ci:region:belier')->name)->toBe('Bélier')
        ->and($byId->get('ci:region:san-pedro')->name)->toBe('San-Pédro')
        ->and($byId->get('ci:region:folon')->name)->toBe('Folon');
});
