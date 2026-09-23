<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiAddressFormatter;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiGeographyProvider;

it('formats Djiboutian addresses with the postcode left of the locality', function (): void {
    $formatted = app(DjiboutiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1663',
        'city' => 'DJIBOUTI VILLE',
        'postcode' => '77101',
        'country_code' => 'DJ',
    ]));

    expect($formatted)->toBe("BP 1663\n77101 DJIBOUTI VILLE\nDjibouti");
});
it('prints matching Djiboutian city and region once', function (): void {
    $formatted = app(DjiboutiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 12',
        'city' => 'Arta',
        'state' => 'Arta',
        'postcode' => '77201',
        'country_code' => 'DJ',
    ]));

    expect($formatted)->toBe("BP 12\n77201 Arta\nDjibouti");
});

it('ships 20 sub-prefectures under regions with parent links', function (): void {
    $areas = app(DjiboutiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(20)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'dj:region:ali-sabieh'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'dj:region:arta'))->toHaveCount(2)
        ->and($l2->where('parentSourceId', 'dj:region:dikhil'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'dj:city:djibouti'))->toHaveCount(1)
        ->and($l2->where('parentSourceId', 'dj:region:obock'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'dj:region:tadjourah'))->toHaveCount(6)
        ->and($byId->get('dj:subprefecture:holhol')->parentSourceId)->toBe('dj:region:ali-sabieh')
        ->and($byId->get('dj:subprefecture:lac-assal')->parentSourceId)->toBe('dj:region:arta')
        ->and($byId->get('dj:subprefecture:adailou')->parentSourceId)->toBe('dj:region:tadjourah');
});

it('labels tiers Région, Ville and Sous-préfecture', function (): void {
    $provider = app(DjiboutiGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Région', 'city' => 'Ville', 'subprefecture' => 'Sous-préfecture'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
