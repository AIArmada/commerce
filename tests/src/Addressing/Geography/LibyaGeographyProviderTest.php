<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Libya\LibyaAddressFormatter;
use AIArmada\Addressing\Geography\Libya\LibyaGeographyProvider;

it('formats Libyan addresses without a postcode system', function (): void {
    $formatted = app(LibyaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. Al Ghazaly 12',
        'city' => 'TRIPOLI',
        'country_code' => 'LY',
    ]));

    expect($formatted)->toBe("Av. Al Ghazaly 12\nTRIPOLI\nLibya");
});
it('prints any supplied Libyan code on its own line', function (): void {
    $formatted = app(LibyaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. Al Ghazaly 12',
        'city' => 'TRIPOLI',
        'postcode' => '99999',
        'country_code' => 'LY',
    ]));

    expect($formatted)->toBe("Av. Al Ghazaly 12\nTRIPOLI\n99999\nLibya");
});

it('ships 100 DTM baladiyas under popularates with parent links', function (): void {
    $areas = app(LibyaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(100)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'ly:popularate:jabal-al-gharbi'))->toHaveCount(14)
        ->and($l2->where('parentSourceId', 'ly:popularate:tripoli'))->toHaveCount(6)
        ->and($l2->where('parentSourceId', 'ly:popularate:al-wahat'))->toHaveCount(6)
        ->and($byId->get('ly:baladiya:tajoura')->code)->toBe('LY021102')
        ->and($byId->get('ly:baladiya:bani-waleed')->parentSourceId)->toBe('ly:popularate:misrata');
});
