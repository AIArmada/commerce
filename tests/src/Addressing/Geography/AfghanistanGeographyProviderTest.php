<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Afghanistan\AfghanistanAddressFormatter;
use AIArmada\Addressing\Geography\Afghanistan\AfghanistanGeographyProvider;

it('formats Afghan addresses with the postcode left of the province', function (): void {
    $formatted = app(AfghanistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'House No 123, Street 5',
        'city' => 'HESARAK',
        'state' => 'NANGARHAR',
        'postcode' => '265101',
        'country_code' => 'AF',
    ]));

    expect($formatted)->toBe("House No 123, Street 5\nHESARAK\n265101 NANGARHAR\nAfghanistan");
});

it('spells the province Uruzgan per the list page (ISO AF-URU)', function (): void {
    $areas = app(AfghanistanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('af:province:uruzgan')->name)->toBe('Uruzgan')
        ->and($areas->get('af:province:uruzgan')->code)->toBe('URU')
        ->and($areas->has('af:province:urozgan'))->toBeFalse();
});

it('ships 401 COD-AB districts under provinces with parent links', function (): void {
    $areas = app(AfghanistanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(401)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'af:province:badakhshan'))->toHaveCount(28)
        ->and($l2->where('parentSourceId', 'af:province:kabul'))->toHaveCount(15)
        ->and($byId->get('af:district:kabul')->code)->toBe('AF0101')
        ->and($byId->get('af:district:ghazni:jaghatu')->parentSourceId)->toBe('af:province:ghazni');
});
