<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaAddressFormatter;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaGeographyProvider;

it('formats Mauritanian addresses without a postcode system', function (): void {
    $formatted = app(MauritaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 35',
        'city' => 'NOUAKCHOTT',
        'country_code' => 'MR',
    ]));

    expect($formatted)->toBe("B.P. 35\nNOUAKCHOTT\nMauritania");
});
it('prints any supplied Mauritanian code on its own line', function (): void {
    $formatted = app(MauritaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 35',
        'city' => 'NOUAKCHOTT',
        'postcode' => '99999',
        'country_code' => 'MR',
    ]));

    expect($formatted)->toBe("B.P. 35\nNOUAKCHOTT\n99999\nMauritania");
});

it('ships 63 departments under regions with parent links', function (): void {
    $areas = app(MauritaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'department');

    expect($l2)->toHaveCount(63)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('mr:department:adel-bagrou')->name)->toBe('Adel Bagrou')
        ->and($byId->get('mr:department:aioun')->name)->toBe('Aïoun')
        ->and($byId->get('mr:department:atar')->name)->toBe('Atar');
});

it('labels tiers Wilaya and Moughataa', function (): void {
    $provider = app(MauritaniaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Wilaya', 'department' => 'Moughataa'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
