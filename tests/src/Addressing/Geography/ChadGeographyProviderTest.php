<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Chad\ChadAddressFormatter;
use AIArmada\Addressing\Geography\Chad\ChadGeographyProvider;

it('formats Chadian addresses without a postcode system', function (): void {
    $formatted = app(ChadAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 4148',
        'city' => 'NDJAMENA',
        'country_code' => 'TD',
    ]));

    expect($formatted)->toBe("BP 4148\nNDJAMENA\nChad");
});
it('formats Chadian addresses with the province below the locality', function (): void {
    $formatted = app(ChadAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenue des Martyrs',
        'city' => 'Moundou',
        'state' => 'Logone Occidental',
        'country_code' => 'TD',
    ]));

    expect($formatted)->toBe("Avenue des Martyrs\nMoundou\nLogone Occidental\nChad");
});

it('ships 63 departments under provinces with parent links', function (): void {
    $areas = app(ChadGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'department');

    expect($l2)->toHaveCount(63)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('td:department:fitri')->name)->toBe('Fitri')
        ->and($byId->get('td:department:fada')->name)->toBe('Fada')
        ->and($byId->get('td:department:am-djarass')->name)->toBe('Am-Djarass');
});

it('labels tiers Province and Département', function (): void {
    $provider = app(ChadGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Province', 'department' => 'Département'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
