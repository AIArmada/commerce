<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Nepal\NepalAddressFormatter;
use AIArmada\Addressing\Geography\Nepal\NepalGeographyProvider;

it('formats Nepali addresses with the postcode right of the locality', function (): void {
    $formatted = app(NepalAddressFormatter::class)->format(AddressData::from([
        'line1' => '102, Mitery Marg',
        'line2' => 'Baneshwore',
        'city' => 'KATHMANDU',
        'state' => 'Bagmati',
        'postcode' => '44601',
        'country_code' => 'NP',
    ]));

    expect($formatted)->toBe("102, Mitery Marg\nBaneshwore\nKATHMANDU 44601\nBagmati\nNepal");
});
it('formats Nepali addresses without a postcode when missing', function (): void {
    $formatted = app(NepalAddressFormatter::class)->format(AddressData::from([
        'line1' => '102, Mitery Marg',
        'city' => 'KATHMANDU',
        'state' => 'Bagmati',
        'country_code' => 'NP',
    ]));

    expect($formatted)->toBe("102, Mitery Marg\nKATHMANDU\nBagmati\nNepal");
});

it('ships 77 districts under provinces with parent links', function (): void {
    $areas = app(NepalGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(77)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('np:district:kathmandu')->name)->toBe('Kathmandu')
        ->and($byId->get('np:district:kaski')->name)->toBe('Kaski')
        ->and($byId->get('np:district:jhapa')->name)->toBe('Jhapa');
});
