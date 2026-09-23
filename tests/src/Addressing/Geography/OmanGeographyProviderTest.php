<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Oman\OmanAddressFormatter;
use AIArmada\Addressing\Geography\Oman\OmanGeographyProvider;

it('formats Omani addresses with the postcode above the locality', function (): void {
    $formatted = app(OmanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 15',
        'city' => 'AL-KHOER',
        'postcode' => '133',
        'country_code' => 'OM',
    ]));

    expect($formatted)->toBe("P.O. Box 15\n133\nAL-KHOER\nOman");
});

it('ships 63 wilayats under governorates with parent links', function (): void {
    $areas = app(OmanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'wilayat');

    expect($l2)->toHaveCount(63)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('om:wilayat:adam')->name)->toBe('Adam')
        ->and($byId->get('om:wilayat:al-hamra')->parentSourceId)->toBe('om:governorate:ad-dakhiliyah');
});

it('labels governorates Muhafaza', function (): void {
    $provider = app(OmanGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['governorate' => 'Muhafaza'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
