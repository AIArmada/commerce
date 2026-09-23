<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\India\IndiaAddressFormatter;
use AIArmada\Addressing\Geography\India\IndiaGeographyProvider;

it('formats Indian addresses with locality, state and postcode lines', function (): void {
    $formatted = app(IndiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '4, Amrita Shergill Road',
        'city' => 'New Delhi',
        'state' => 'Delhi',
        'postcode' => '110003',
        'country_code' => 'IN',
    ]));

    expect($formatted)->toBe("4, Amrita Shergill Road\nNew Delhi\nDelhi\n110003\nIndia");
});

it('ships 786 districts under states and union territories with parent links', function (): void {
    $areas = app(IndiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($areas->where('type', 'state'))->toHaveCount(28)
        ->and($areas->where('type', 'union_territory'))->toHaveCount(8)
        ->and($l2)->toHaveCount(786)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('in:district:602')->name)->toBe('South Andaman');
});
