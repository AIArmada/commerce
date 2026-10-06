<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Turkiye\TurkiyeGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Turkiye tree of 81 provinces and 973 districts', function (): void {
    $areas = app(TurkiyeGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'province'))->toHaveCount(81)
        ->and($areas->where('type', 'district'))->toHaveCount(973)
        ->and($areas->where('level', 1))->toHaveCount(81)
        ->and($areas->where('level', 2))->toHaveCount(973)
        ->and($areas->where('name', 'Merkez'))->toHaveCount(51);

    // ISO 3166-2:TR plate-code spot pins.
    expect($byId->get('tr:province:adana')->code)->toBe('01')
        ->and($byId->get('tr:province:ankara')->code)->toBe('06')
        ->and($byId->get('tr:province:hakkari')->code)->toBe('30')
        ->and($byId->get('tr:province:istanbul')->code)->toBe('34')
        ->and($byId->get('tr:province:izmir')->code)->toBe('35')
        ->and($byId->get('tr:province:duzce')->code)->toBe('81')
        ->and($byId->get('tr:district:adana:ceyhan')->parentSourceId)->toBe('tr:province:adana');

    // Post-2012 split districts and stale-spelling districts.
    expect($byId->get('tr:district:hakkari:derecik')->parentSourceId)->toBe('tr:province:hakkari')
        ->and($byId->get('tr:district:aksaray:sultanhan')->parentSourceId)->toBe('tr:province:aksaray')
        ->and($byId->get('tr:district:artvin:kemalpasa')->parentSourceId)->toBe('tr:province:artvin')
        ->and($byId->get('tr:district:balkesir:gomec')->parentSourceId)->toBe('tr:province:balkesir')
        ->and($byId->get('tr:district:siirt:tillo')->name)->toBe('Tillo')
        ->and($byId->get('tr:district:agr:dogubayazt')->name)->toBe('Doğubayazıt')
        ->and($byId->get('tr:district:kahramanmaras:caglayancerit')->name)->toBe('Çağlayancerit');

    // Per-province district counts (Districts-of-Turkey tables + Merkez).
    $expected = [
        'tr:province:adana' => 15, 'tr:province:adyaman' => 9, 'tr:province:afyonkarahisar' => 18,
        'tr:province:agr' => 8, 'tr:province:amasya' => 7, 'tr:province:ankara' => 25,
        'tr:province:antalya' => 19, 'tr:province:artvin' => 9, 'tr:province:aydn' => 17,
        'tr:province:balkesir' => 20, 'tr:province:bilecik' => 8, 'tr:province:bingol' => 8,
        'tr:province:bitlis' => 7, 'tr:province:bolu' => 9, 'tr:province:burdur' => 11,
        'tr:province:bursa' => 17, 'tr:province:canakkale' => 12, 'tr:province:cankr' => 12,
        'tr:province:corum' => 14, 'tr:province:denizli' => 19, 'tr:province:diyarbakr' => 17,
        'tr:province:edirne' => 9, 'tr:province:elazg' => 11, 'tr:province:erzincan' => 9,
        'tr:province:erzurum' => 20, 'tr:province:eskisehir' => 14, 'tr:province:gaziantep' => 9,
        'tr:province:giresun' => 16, 'tr:province:gumushane' => 6, 'tr:province:hakkari' => 5,
        'tr:province:hatay' => 15, 'tr:province:isparta' => 13, 'tr:province:mersin' => 13,
        'tr:province:istanbul' => 39, 'tr:province:izmir' => 30, 'tr:province:kars' => 8,
        'tr:province:kastamonu' => 20, 'tr:province:kayseri' => 16, 'tr:province:krklareli' => 8,
        'tr:province:krsehir' => 7, 'tr:province:kocaeli' => 12, 'tr:province:konya' => 31,
        'tr:province:kutahya' => 13, 'tr:province:malatya' => 13, 'tr:province:manisa' => 17,
        'tr:province:kahramanmaras' => 11, 'tr:province:mardin' => 10, 'tr:province:mugla' => 13,
        'tr:province:mus' => 6, 'tr:province:nevsehir' => 8, 'tr:province:nigde' => 6,
        'tr:province:ordu' => 19, 'tr:province:rize' => 12, 'tr:province:sakarya' => 16,
        'tr:province:samsun' => 17, 'tr:province:siirt' => 7, 'tr:province:sinop' => 9,
        'tr:province:sivas' => 17, 'tr:province:tekirdag' => 11, 'tr:province:tokat' => 12,
        'tr:province:trabzon' => 18, 'tr:province:tunceli' => 8, 'tr:province:sanlurfa' => 13,
        'tr:province:usak' => 6, 'tr:province:van' => 13, 'tr:province:yozgat' => 14,
        'tr:province:zonguldak' => 8, 'tr:province:aksaray' => 8, 'tr:province:bayburt' => 3,
        'tr:province:karaman' => 6, 'tr:province:krkkale' => 9, 'tr:province:batman' => 6,
        'tr:province:srnak' => 7, 'tr:province:bartn' => 4, 'tr:province:ardahan' => 6,
        'tr:province:igdr' => 4, 'tr:province:yalova' => 6, 'tr:province:karabuk' => 6,
        'tr:province:kilis' => 4, 'tr:province:osmaniye' => 7, 'tr:province:duzce' => 8,
    ];

    foreach ($expected as $province => $count) {
        expect($areas->where('parentSourceId', $province))->toHaveCount($count, $province);
    }
});

it('bundles the verified 2896-code overlay with 7 adjudicated duals', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('TR', $dir . '/turkiye-postal-codes.csv', $dir . '/turkiye-postal-code-areas.csv', 'aiarmada.addressing.turkiye');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(2903)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(2896)
        ->and($postcodes->where('isPrimary', false))->toHaveCount(7)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(2896)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => ! str_starts_with((string) $row->code, '99')))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Triple-corroborated anchors (GeoNames + town page + OSM where covered).
    expect($primary('01010'))->toBe('tr:district:adana:seyhan')
        ->and($primary('06050'))->toBe('tr:district:ankara:altndag')
        ->and($primary('34010'))->toBe('tr:district:istanbul:zeytinburnu')
        ->and($primary('07010'))->toBe('tr:district:antalya:muratpasa')
        ->and($primary('61000'))->toBe('tr:district:trabzon:ortahisar')
        ->and($primary('63000'))->toBe('tr:district:sanlurfa:eyyubiye')
        ->and($primary('10715'))->toBe('tr:district:balkesir:gomec')
        ->and($primary('59520'))->toBe('tr:district:tekirdag:kapakl')
        ->and($primary('38310'))->toBe('tr:district:kayseri:talas');

    // Parent-district codes that serve the three codeless split-off districts.
    expect($primary('30800'))->toBe('tr:district:hakkari:semdinli')
        ->and($primary('68190'))->toBe('tr:district:aksaray:merkez')
        ->and($primary('08610'))->toBe('tr:district:artvin:hopa');

    // All 7 shared codes: genuinely dual-district, primaries kept.
    $secondary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', false)->areaSourceId;

    expect($primary('09670'))->toBe('tr:district:aydn:buharkent')
        ->and($secondary('09670'))->toBe('tr:district:aydn:kocarl')
        ->and($primary('16270'))->toBe('tr:district:bursa:osmangazi')
        ->and($secondary('16270'))->toBe('tr:district:bursa:yldrm')
        ->and($primary('16370'))->toBe('tr:district:bursa:osmangazi')
        ->and($secondary('16370'))->toBe('tr:district:bursa:yldrm')
        ->and($primary('19800'))->toBe('tr:district:corum:bayat')
        ->and($secondary('19800'))->toBe('tr:district:corum:dodurga')
        ->and($primary('35730'))->toBe('tr:district:izmir:kemalpasa')
        ->and($secondary('35730'))->toBe('tr:district:izmir:bergama')
        ->and($primary('44000'))->toBe('tr:district:malatya:yesilyurt')
        ->and($secondary('44000'))->toBe('tr:district:malatya:battalgazi')
        ->and($primary('55530'))->toBe('tr:district:samsun:salpazar')
        ->and($secondary('55530'))->toBe('tr:district:samsun:tekkekoy');
});
