<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Uganda\UgandaAddressFormatter;
use AIArmada\Addressing\Geography\Uganda\UgandaGeographyProvider;

it('formats Ugandan addresses with the postcode left of the locality', function (): void {
    $formatted = app(UgandaAddressFormatter::class)->format(AddressData::from([
        'line1' => '22 Siad Barre Avenue',
        'city' => 'KAMPALA',
        'postcode' => '10000',
        'country_code' => 'UG',
    ]));

    expect($formatted)->toBe("22 Siad Barre Avenue\n10000 KAMPALA\nUganda");
});

it('ships 135 districts and 11 cities under regions with parent links', function (): void {
    $areas = app(UgandaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(146)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('type', 'city')->where('level', 2))->toHaveCount(11)
        ->and($byId->get('ug:city:kampala')->name)->toBe('Kampala')
        ->and($byId->get('ug:city:arua')->name)->toBe('Arua')
        ->and($byId->get('ug:district:madi-okollo')->name)->toBe('Madi-Okollo')
        ->and($byId->get('ug:district:rwampara')->name)->toBe('Rwampara');
});
