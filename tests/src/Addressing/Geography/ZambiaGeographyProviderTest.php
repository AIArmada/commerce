<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Zambia\ZambiaAddressFormatter;
use AIArmada\Addressing\Geography\Zambia\ZambiaGeographyProvider;

it('formats Zambian addresses with the postcode right of the locality', function (): void {
    $formatted = app(ZambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Independence Avenue',
        'city' => 'KITWE',
        'postcode' => '23456',
        'country_code' => 'ZM',
    ]));

    expect($formatted)->toBe("21 Independence Avenue\nKITWE 23456\nZambia");
});
it('formats Zambian addresses omitting the routinely skipped postcode', function (): void {
    $formatted = app(ZambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Independence Avenue',
        'city' => 'LUSAKA',
        'state' => 'Lusaka',
        'country_code' => 'ZM',
    ]));

    expect($formatted)->toBe("21 Independence Avenue\nLUSAKA\nZambia");
});

it('ships 116 districts under provinces with parent links', function (): void {
    $areas = app(ZambiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(116)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('zm:district:kabwe')->name)->toBe('Kabwe')
        ->and($byId->get('zm:district:ngabwe')->name)->toBe('Ngabwe')
        ->and($byId->get('zm:district:shibuyunji')->name)->toBe('Shibuyunji');
});
