<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanAddressFormatter;
use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanGeographyProvider;

it('formats Uzbek addresses with the postcode and comma left of the locality', function (): void {
    $formatted = app(UzbekistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'pr-t Mustakillik, d. 5, kv. 12',
        'city' => 'g. Tashkent 123',
        'postcode' => '100123',
        'country_code' => 'UZ',
    ]));

    expect($formatted)->toBe("pr-t Mustakillik, d. 5, kv. 12\n100123, g. Tashkent 123\nUzbekistan");
});

it('spells the Xorazm tuman Tuproqqal\'a', function (): void {
    $areas = app(UzbekistanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('uz:tuman:xorazm:tuproqqala')->name)->toBe('Tuproqqal\'a')
        ->and($areas->has('uz:tuman:xorazm:toproqqala'))->toBeFalse();
});

it('ships 175 tumans and 31 cities under regions with parent links', function (): void {
    $areas = app(UzbekistanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('type', 'tuman'))->toHaveCount(175)
        ->and($l2->where('type', 'city'))->toHaveCount(31)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();
});

it('labels cities Shahar', function (): void {
    $provider = app(UzbekistanGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['city' => 'Shahar'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
