<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guinea\GuineaAddressFormatter;
use AIArmada\Addressing\Geography\Guinea\GuineaGeographyProvider;

it('formats Guinean addresses with the radical left of the locality', function (): void {
    $formatted = app(GuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 457',
        'city' => 'CONAKRY',
        'postcode' => '001',
        'country_code' => 'GN',
    ]));

    expect($formatted)->toBe("BP 457\n001 CONAKRY\nGuinea");
});
it('prints matching Guinean city and region once', function (): void {
    $formatted = app(GuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 12',
        'city' => 'Labé',
        'state' => 'Labé',
        'postcode' => '201',
        'country_code' => 'GN',
    ]));

    expect($formatted)->toBe("BP 12\n201 Labé\nGuinea");
});

it('ships 33 prefectures under regions with parent links', function (): void {
    $areas = app(GuineaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'prefecture');

    expect($l2)->toHaveCount(33)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('gn:prefecture:boke')->name)->toBe('Boké')
        ->and($byId->get('gn:prefecture:beyla')->parentSourceId)->toBe('gn:administrative_region:nzerekore');
});

it('labels tiers Région, Gouvernorat and Préfecture', function (): void {
    $provider = app(GuineaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['administrative_region' => 'Région', 'governorate' => 'Gouvernorat', 'prefecture' => 'Préfecture'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
