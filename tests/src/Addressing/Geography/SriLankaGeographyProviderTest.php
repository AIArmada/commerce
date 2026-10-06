<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SriLanka\SriLankaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 25 districts under provinces with parent links', function (): void {
    $areas = app(SriLankaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(25)
        ->and($byId->get('lk:district:ampara')->parentSourceId)->toBe('lk:province:eastern');
});

it('pins the verified Sri Lanka tree of 9 provinces and 25 districts', function (): void {
    $areas = app(SriLankaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'province'))->toHaveCount(9)
        ->and($areas->where('type', 'district'))->toHaveCount(25)
        ->and($areas->where('parentSourceId', 'lk:province:western'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'lk:province:central'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'lk:province:southern'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'lk:province:northern'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'lk:province:eastern'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'lk:province:north-western'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'lk:province:north-central'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'lk:province:uva'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'lk:province:sabaragamuwa'))->toHaveCount(2);

    // ISO 3166-2:LK oracle: province digits 1-9, district codes LK-11..LK-92.
    expect($byId->get('lk:province:western')->code)->toBe('1')
        ->and($byId->get('lk:province:sabaragamuwa')->code)->toBe('9')
        ->and($byId->get('lk:district:colombo')->code)->toBe('11')
        ->and($byId->get('lk:district:kegalle')->code)->toBe('92')
        ->and($byId->get('lk:district:mullaitivu')->code)->toBe('45')
        ->and($byId->get('lk:district:mullaitivu')->parentSourceId)->toBe('lk:province:northern')
        ->and($byId->get('lk:district:nuwara-eliya')->parentSourceId)->toBe('lk:province:central')
        ->and($byId->get('lk:district:monaragala')->parentSourceId)->toBe('lk:province:uva');
});

it('bundles the verified 2121-code overlay with exact primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('LK', $dir . '/sri-lanka-postal-codes.csv', $dir . '/sri-lanka-postal-code-areas.csv', 'aiarmada.addressing.sri_lanka');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(2121)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(2121)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(2121)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Colombo 01-15 cluster: SL Post book + online lookup list 00200/00500/
    // 00600/00800/01300 as offices; calllanka + advice.lk confirm all 15
    // zones (the rest are delivery sectors, not separate directory entries).
    foreach (['00100', '00200', '00300', '00400', '00500', '00600', '00700', '00800', '00900', '01000', '01100', '01200', '01300', '01400', '01500'] as $code) {
        expect($primary($code))->toBe('lk:district:colombo');
    }

    // District main-office anchors: SL Post book + online + GeoNames agree.
    expect($primary('40000'))->toBe('lk:district:jaffna')
        ->and($primary('20000'))->toBe('lk:district:kandy')
        ->and($primary('80000'))->toBe('lk:district:galle')
        ->and($primary('50000'))->toBe('lk:district:anuradhapura')
        ->and($primary('60000'))->toBe('lk:district:kurunegala')
        ->and($primary('70000'))->toBe('lk:district:ratnapura')
        ->and($primary('90000'))->toBe('lk:district:badulla')
        ->and($primary('30000'))->toBe('lk:district:batticaloa')
        ->and($primary('31000'))->toBe('lk:district:trincomalee')
        ->and($primary('32000'))->toBe('lk:district:ampara');

    // Cross-block keeps: Pugoda 106xx (Gampaha, not Colombo),
    // Puthukkudiyiruppu 425xx (Mullaitivu, not Kilinochchi), Bogaswewa
    // 43583 (Vavuniya outlier), Kalmunai-sub-range 32198 (Ampara).
    expect($primary('10660'))->toBe('lk:district:gampaha')
        ->and($primary('42530'))->toBe('lk:district:mullaitivu')
        ->and($primary('43583'))->toBe('lk:district:vavuniya')
        ->and($primary('32198'))->toBe('lk:district:ampara')
        ->and($primary('22680'))->toBe('lk:district:nuwara-eliya')
        ->and($primary('22748'))->toBe('lk:district:nuwara-eliya');

    // Per-district primary counts (B7 bulk-verified against SL Post oracles).
    $counts = $postcodes->where('isPrimary', true)->countBy(static fn ($row): string => (string) $row->areaSourceId);
    $expected = ['lk:district:ampara' => 67, 'lk:district:anuradhapura' => 134, 'lk:district:badulla' => 145, 'lk:district:batticaloa' => 48, 'lk:district:colombo' => 71, 'lk:district:galle' => 96, 'lk:district:gampaha' => 134, 'lk:district:hambantota' => 67, 'lk:district:jaffna' => 51, 'lk:district:kalutara' => 84, 'lk:district:kandy' => 179, 'lk:district:kegalle' => 102, 'lk:district:kilinochchi' => 28, 'lk:district:kurunegala' => 217, 'lk:district:mannar' => 25, 'lk:district:matale' => 74, 'lk:district:matara' => 82, 'lk:district:monaragala' => 66, 'lk:district:mullaitivu' => 18, 'lk:district:nuwara-eliya' => 79, 'lk:district:polonnaruwa' => 68, 'lk:district:puttalam' => 90, 'lk:district:ratnapura' => 132, 'lk:district:trincomalee' => 43, 'lk:district:vavuniya' => 21];
    foreach ($expected as $area => $count) {
        expect($counts->get($area))->toBe($count);
    }

    // Rejected stale GeoNames codes stay absent: Nuwara Eliya 205xx/206xx/
    // 207xx renumbered to 225xx/226xx/227xx, 22040->22042, 50567->31017,
    // 702xx->912xx, 81318->81308, 82401->82104, 82586->82506,
    // 91040/91042->32040/32042, 96167->90167; 20186/20568/20684/32155/
    // 70254 have no SL Post office behind them (closed or never postal).
    foreach (['20186', '20560', '20566', '20567', '20568', '20588', '20590', '20592', '20660', '20668', '20669', '20670', '20678', '20680', '20682', '20684', '20686', '20688', '20742', '20744', '20748', '20750', '20752', '22040', '32155', '50567', '70252', '70254', '70256', '81318', '82401', '82586', '91040', '91042', '96167'] as $code) {
        expect($byCode->has($code))->toBeFalse();
    }
});
