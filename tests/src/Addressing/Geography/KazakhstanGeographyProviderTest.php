<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Kazakhstan tree of 17 regions, 3 cities, and 170 districts', function (): void {
    $areas = app(KazakhstanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(190)
        ->and($areas->where('type', 'region'))->toHaveCount(17)
        ->and($areas->where('type', 'city'))->toHaveCount(3)
        ->and($areas->where('type', 'district'))->toHaveCount(170)
        ->and($areas->where('parentSourceId', 'kz:region:abai'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'kz:region:akmola'))->toHaveCount(17)
        ->and($areas->where('parentSourceId', 'kz:region:aktobe'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'kz:region:almaty'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'kz:city:almaty'))->toHaveCount(0)
        ->and($areas->where('parentSourceId', 'kz:region:atyrau'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'kz:region:east-kazakhstan'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'kz:region:jambyl'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'kz:region:jetisu'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'kz:region:karaganda'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'kz:region:kostanay'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'kz:region:kyzylorda'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'kz:region:mangystau'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'kz:region:north-kazakhstan'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'kz:region:pavlodar'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'kz:region:turkistan'))->toHaveCount(14)
        ->and($areas->where('parentSourceId', 'kz:region:ulytau'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'kz:region:west-kazakhstan'))->toHaveCount(12);

    // ISO 3166-2:KZ codes (2022 scheme: numeric, incl. Abai/Jetisu/Ulytau).
    expect($byId->get('kz:region:abai')->code)->toBe('10')
        ->and($byId->get('kz:region:akmola')->code)->toBe('11')
        ->and($byId->get('kz:region:aktobe')->code)->toBe('15')
        ->and($byId->get('kz:region:almaty')->code)->toBe('19')
        ->and($byId->get('kz:city:almaty')->code)->toBe('75')
        ->and($byId->get('kz:city:astana')->code)->toBe('71')
        ->and($byId->get('kz:region:atyrau')->code)->toBe('23')
        ->and($byId->get('kz:region:east-kazakhstan')->code)->toBe('63')
        ->and($byId->get('kz:region:jambyl')->code)->toBe('31')
        ->and($byId->get('kz:region:jetisu')->code)->toBe('33')
        ->and($byId->get('kz:region:karaganda')->code)->toBe('35')
        ->and($byId->get('kz:region:kostanay')->code)->toBe('39')
        ->and($byId->get('kz:region:kyzylorda')->code)->toBe('43')
        ->and($byId->get('kz:region:mangystau')->code)->toBe('47')
        ->and($byId->get('kz:region:north-kazakhstan')->code)->toBe('59')
        ->and($byId->get('kz:region:pavlodar')->code)->toBe('55')
        ->and($byId->get('kz:city:shymkent')->code)->toBe('79')
        ->and($byId->get('kz:region:turkistan')->code)->toBe('61')
        ->and($byId->get('kz:region:ulytau')->code)->toBe('62')
        ->and($byId->get('kz:region:west-kazakhstan')->code)->toBe('27');

    // M6 fix: the 9 Almaty-region audandar parent to the region, not the city.
    foreach (['balkhash', 'enbekshikazakh', 'ile', 'karasay', 'kegen', 'raiymbek', 'talgar', 'uygur', 'zhambyl'] as $slug) {
        expect($byId->get("kz:district:{$slug}")->parentSourceId)->toBe('kz:region:almaty');
    }
});

it('bundles the 2525 KZ postcodes as L1 singleton links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('KZ', $dir . '/kazakhstan-postal-codes.csv', $dir . '/kazakhstan-postal-code-areas.csv', 'aiarmada.addressing.kazakhstan');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(2525)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{6}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(2525)
        ->and($postcodes->every(static fn ($row): bool => ! str_starts_with((string) $row->code, '7')))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // UPU KAZ anchors: Astana 010000/010013.
    expect((string) $byCode->get('010013')->areaSourceId)->toBe('kz:city:astana')
        ->and((string) $byCode->get('010000')->areaSourceId)->toBe('kz:city:astana');

    // Build corrections: 0218xx Akmola + 151309 North Kazakhstan.
    expect((string) $byCode->get('021800')->areaSourceId)->toBe('kz:region:akmola')
        ->and((string) $byCode->get('021812')->areaSourceId)->toBe('kz:region:akmola')
        ->and((string) $byCode->get('151309')->areaSourceId)->toBe('kz:region:north-kazakhstan');

    // Nominatim adjudications + Samar split + Shymkent enclave.
    expect((string) $byCode->get('040200')->areaSourceId)->toBe('kz:region:jetisu')
        ->and((string) $byCode->get('040313')->areaSourceId)->toBe('kz:region:almaty')
        ->and((string) $byCode->get('040213')->areaSourceId)->toBe('kz:region:jetisu')
        ->and((string) $byCode->get('071005')->areaSourceId)->toBe('kz:region:abai')
        ->and((string) $byCode->get('071007')->areaSourceId)->toBe('kz:region:east-kazakhstan')
        ->and((string) $byCode->get('160818')->areaSourceId)->toBe('kz:city:shymkent');

    // M6 flip: 070209 Tarbagatai joins its Ayagoz block in Abai.
    expect((string) $byCode->get('070209')->areaSourceId)->toBe('kz:region:abai');

    // Thin spots stay thin (documented gaps).
    expect($postcodes->where('areaSourceId', 'kz:region:ulytau')->pluck('code')->sort()->values()->all())
        ->toBe(['100700', '100701', '100702'])
        ->and($postcodes->where('areaSourceId', 'kz:city:shymkent')->pluck('code')->all())->toBe(['160818']);

    // Per-L1 counts.
    $counts = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts->get('kz:region:jambyl'))->toBe(227)
        ->and($counts->get('kz:region:akmola'))->toBe(222)
        ->and($counts->get('kz:region:karaganda'))->toBe(210)
        ->and($counts->get('kz:region:north-kazakhstan'))->toBe(203)
        ->and($counts->get('kz:region:turkistan'))->toBe(189)
        ->and($counts->get('kz:region:kostanay'))->toBe(182)
        ->and($counts->get('kz:region:aktobe'))->toBe(168)
        ->and($counts->get('kz:region:pavlodar'))->toBe(159)
        ->and($counts->get('kz:region:west-kazakhstan'))->toBe(156)
        ->and($counts->get('kz:region:kyzylorda'))->toBe(141)
        ->and($counts->get('kz:region:almaty'))->toBe(135)
        ->and($counts->get('kz:region:jetisu'))->toBe(115)
        ->and($counts->get('kz:region:atyrau'))->toBe(105)
        ->and($counts->get('kz:region:east-kazakhstan'))->toBe(91)
        ->and($counts->get('kz:region:abai'))->toBe(81)
        ->and($counts->get('kz:city:almaty'))->toBe(67)
        ->and($counts->get('kz:region:mangystau'))->toBe(48)
        ->and($counts->get('kz:city:astana'))->toBe(22)
        ->and($counts->get('kz:region:ulytau'))->toBe(3)
        ->and($counts->get('kz:city:shymkent'))->toBe(1);
});
