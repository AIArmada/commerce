<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Eritrea\EritreaAddressFormatter;
use AIArmada\Addressing\Geography\Eritrea\EritreaGeographyProvider;

it('formats Eritrean addresses without a postcode system', function (): void {
    $formatted = app(EritreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Awet Street 4',
        'city' => 'ASMARA',
        'country_code' => 'ER',
    ]));

    expect($formatted)->toBe("Awet Street 4\nASMARA\nEritrea");
});
it('prints any supplied Eritrean code on its own line', function (): void {
    $formatted = app(EritreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Awet Street 4',
        'city' => 'ASMARA',
        'postcode' => '99999',
        'country_code' => 'ER',
    ]));

    expect($formatted)->toBe("Awet Street 4\nASMARA\n99999\nEritrea");
});

it('ships 58 subregions under regions with parent links', function (): void {
    $areas = app(EritreaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'subregion');

    expect($l2)->toHaveCount(58)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('er:subregion:keren')->name)->toBe('Keren')
        ->and($byId->get('er:subregion:adi-quala')->name)->toBe('Adi Quala')
        ->and($byId->get('er:subregion:massawa')->name)->toBe('Massawa');
});

it('labels regions Zoba', function (): void {
    $provider = app(EritreaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Zoba'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
