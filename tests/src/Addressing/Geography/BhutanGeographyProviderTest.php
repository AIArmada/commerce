<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bhutan\BhutanAddressFormatter;
use AIArmada\Addressing\Geography\Bhutan\BhutanGeographyProvider;

it('formats Bhutanese addresses with the postcode right of the locality', function (): void {
    $formatted = app(BhutanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Chang Lam, JD House',
        'line2' => 'Flat No. 2B',
        'city' => 'Thimphu',
        'state' => 'Thimphu',
        'postcode' => '11001',
        'country_code' => 'BT',
    ]));

    expect($formatted)->toBe("Chang Lam, JD House\nFlat No. 2B\nThimphu 11001\nBhutan");
});
it('prints matching Bhutanese city and dzongkhag once when the postcode is missing', function (): void {
    $formatted = app(BhutanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Chang Lam, JD House',
        'city' => 'Thimphu',
        'state' => 'Thimphu',
        'country_code' => 'BT',
    ]));

    expect($formatted)->toBe("Chang Lam, JD House\nThimphu\nBhutan");
});

it('ships 205 gewogs under districts with parent links', function (): void {
    $areas = app(BhutanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'gewog');

    expect($l2)->toHaveCount(205)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('bt:gewog:chhoekhor')->name)->toBe('Chhoekhor')
        ->and($byId->get('bt:gewog:bongo')->name)->toBe('Bongo')
        ->and($byId->get('bt:gewog:tang')->name)->toBe('Tang');
});
