<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Afghanistan\AfghanistanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

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

it('pins the 34 ISO 3166-2:AF province codes', function (): void {
    $areas = app(AfghanistanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $iso = [
        'af:province:badakhshan' => 'BDS', 'af:province:badghis' => 'BDG',
        'af:province:baghlan' => 'BGL', 'af:province:balkh' => 'BAL',
        'af:province:bamyan' => 'BAM', 'af:province:daykundi' => 'DAY',
        'af:province:farah' => 'FRA', 'af:province:faryab' => 'FYB',
        'af:province:ghazni' => 'GHA', 'af:province:ghor' => 'GHO',
        'af:province:helmand' => 'HEL', 'af:province:herat' => 'HER',
        'af:province:jowzjan' => 'JOW', 'af:province:kabul' => 'KAB',
        'af:province:kandahar' => 'KAN', 'af:province:kapisa' => 'KAP',
        'af:province:khost' => 'KHO', 'af:province:kunar' => 'KNR',
        'af:province:kunduz' => 'KDZ', 'af:province:laghman' => 'LAG',
        'af:province:logar' => 'LOG', 'af:province:nangarhar' => 'NAN',
        'af:province:nimruz' => 'NIM', 'af:province:nuristan' => 'NUR',
        'af:province:paktia' => 'PIA', 'af:province:paktika' => 'PKA',
        'af:province:panjshir' => 'PAN', 'af:province:parwan' => 'PAR',
        'af:province:samangan' => 'SAM', 'af:province:sar-e-pol' => 'SAR',
        'af:province:takhar' => 'TAK', 'af:province:uruzgan' => 'URU',
        'af:province:wardak' => 'WAR', 'af:province:zabul' => 'ZAB',
    ];

    foreach ($iso as $id => $code) {
        expect($areas->get($id)->code)->toBe($code);
    }
});

it('pins the per-province COD-AB district counts', function (): void {
    $areas = app(AfghanistanGeographyProvider::class)->addressAreaSource()->areas()->collect();

    $counts = [
        'af:province:badakhshan' => 28, 'af:province:badghis' => 7,
        'af:province:baghlan' => 15, 'af:province:balkh' => 15,
        'af:province:bamyan' => 7, 'af:province:daykundi' => 9,
        'af:province:farah' => 11, 'af:province:faryab' => 14,
        'af:province:ghazni' => 19, 'af:province:ghor' => 10,
        'af:province:helmand' => 13, 'af:province:herat' => 16,
        'af:province:jowzjan' => 11, 'af:province:kabul' => 15,
        'af:province:kandahar' => 16, 'af:province:kapisa' => 7,
        'af:province:khost' => 13, 'af:province:kunar' => 15,
        'af:province:kunduz' => 7, 'af:province:laghman' => 5,
        'af:province:logar' => 7, 'af:province:nangarhar' => 22,
        'af:province:nimruz' => 5, 'af:province:nuristan' => 8,
        'af:province:paktia' => 11, 'af:province:paktika' => 19,
        'af:province:panjshir' => 7, 'af:province:parwan' => 10,
        'af:province:samangan' => 8, 'af:province:sar-e-pol' => 7,
        'af:province:takhar' => 17, 'af:province:uruzgan' => 7,
        'af:province:wardak' => 9, 'af:province:zabul' => 11,
    ];

    foreach ($counts as $parent => $n) {
        expect($areas->where('parentSourceId', $parent))->toHaveCount($n);
    }
});

it('bundles the 1408 six-digit postcodes as singleton district primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('AF', $dir . '/afghanistan-postal-codes.csv', $dir . '/afghanistan-postal-code-areas.csv', 'aiarmada.addressing.afghanistan');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(1408)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{6}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(1408);

    $byCode = $postcodes->keyBy->code;

    // UPU afgEn 07/2025 example anchors.
    expect((string) $byCode->get('100208')->areaSourceId)->toBe('af:district:kabul')
        ->and((string) $byCode->get('106401')->areaSourceId)->toBe('af:district:paghman')
        ->and((string) $byCode->get('265101')->areaSourceId)->toBe('af:district:hesarak')
        ->and((string) $byCode->get('385301')->areaSourceId)->toBe('af:district:spin-boldak');

    // M7 moves: Nominatim/OSM-boundary errors corrected to COD-AB geometry.
    expect((string) $byCode->get('295801')->areaSourceId)->toBe('af:district:waygal')
        ->and((string) $byCode->get('306701')->areaSourceId)->toBe('af:district:shindand')
        ->and((string) $byCode->get('326101')->areaSourceId)->toBe('af:district:feroz-koh')
        ->and((string) $byCode->get('396401')->areaSourceId)->toBe('af:district:baghran')
        ->and((string) $byCode->get('186401')->areaSourceId)->toBe('af:district:qaysar')
        ->and((string) $byCode->get('366501')->areaSourceId)->toBe('af:district:pul-e-khumri')
        ->and((string) $byCode->get('346501')->areaSourceId)->toBe('af:district:darwaz-e-payin')
        ->and((string) $byCode->get('216001')->areaSourceId)->toBe('af:district:sar-e-pul')
        ->and((string) $byCode->get('175502')->areaSourceId)->toBe('af:district:sharak-e-hayratan')
        ->and((string) $byCode->get('326201')->areaSourceId)->toBe('af:district:jawand')
        ->and((string) $byCode->get('335801')->areaSourceId)->toBe('af:district:ab-kamari')
        ->and((string) $byCode->get('265702')->areaSourceId)->toBe('af:district:behsud');

    // M7 fills: both "drops" were COD-AB districts (AF3013, AF2507).
    expect((string) $byCode->get('396701')->areaSourceId)->toBe('af:district:deh-e-shu')
        ->and((string) $byCode->get('425201')->areaSourceId)->toBe('af:district:gizab');

    // Verified adjudications (geometry + tag + second signal agree).
    expect((string) $byCode->get('396801')->areaSourceId)->toBe('af:district:nahr-e-saraj')
        ->and((string) $byCode->get('396301')->areaSourceId)->toBe('af:district:nad-e-ali')
        ->and((string) $byCode->get('396601')->areaSourceId)->toBe('af:district:nahr-e-saraj')
        ->and((string) $byCode->get('386701')->areaSourceId)->toBe('af:district:spin-boldak')
        ->and((string) $byCode->get('386601')->areaSourceId)->toBe('af:district:kandahar')
        ->and((string) $byCode->get('225301')->areaSourceId)->toBe('af:district:lija-ahmad-khel')
        ->and((string) $byCode->get('347801')->areaSourceId)->toBe('af:district:wakhan')
        ->and((string) $byCode->get('286601')->areaSourceId)->toBe('af:district:shigal')
        ->and((string) $byCode->get('300101')->areaSourceId)->toBe('af:district:hirat')
        ->and((string) $byCode->get('435501')->areaSourceId)->toBe('af:district:khashrod')
        ->and((string) $byCode->get('186701')->areaSourceId)->toBe('af:district:qaysar');
});
