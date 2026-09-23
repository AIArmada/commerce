<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Liberia\LiberiaAddressFormatter;
use AIArmada\Addressing\Geography\Liberia\LiberiaGeographyProvider;

it('formats Liberian addresses with the postcode left of the locality', function (): void {
    $formatted = app(LiberiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Water Street',
        'city' => 'Buchanan',
        'postcode' => '4000',
        'country_code' => 'LR',
    ]));

    expect($formatted)->toBe("Water Street\n4000 Buchanan\nLiberia");
});
it('formats Liberian addresses without a postcode when missing', function (): void {
    $formatted = app(LiberiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Water Street',
        'city' => 'Monrovia',
        'state' => 'Montserrado',
        'country_code' => 'LR',
    ]));

    expect($formatted)->toBe("Water Street\nMonrovia\nMontserrado\nLiberia");
});

it('ships 127 districts under counties with parent links', function (): void {
    $areas = app(LiberiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(127)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('lr:district:klay')->name)->toBe('Klay')
        ->and($byId->get('lr:district:sanniquellie-mahn')->name)->toBe('Sanniquellie-Mahn')
        ->and($byId->get('lr:district:barclayville')->name)->toBe('Barclayville');
});
