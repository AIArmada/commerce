<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaAddressFormatter;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaGeographyProvider;

it('formats Saudi addresses with the postcode above the locality', function (): void {
    $formatted = app(SaudiArabiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '2929 Rayhanah Bint Zaid',
        'city' => 'RIYADH',
        'postcode' => '13337',
        'country_code' => 'SA',
    ]));

    expect($formatted)->toBe("2929 Rayhanah Bint Zaid\n13337\nRIYADH\nSaudi Arabia");
});

it('ships 139 governorates under regions with parent links', function (): void {
    $areas = app(SaudiArabiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'governorate');

    expect($l2)->toHaveCount(139)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sa:governorate:yanbu')->name)->toBe('Yanbu')
        ->and($byId->get('sa:governorate:taif')->name)->toBe('Taif')
        ->and($byId->get('sa:governorate:umluj')->name)->toBe('Umluj');
});

it('labels tiers Region and Muhafaza', function (): void {
    $provider = app(SaudiArabiaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Region', 'governorate' => 'Muhafaza'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
